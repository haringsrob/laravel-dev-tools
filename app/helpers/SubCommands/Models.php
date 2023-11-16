<?php

use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Soyhuce\NextIdeHelper\Domain\Models\Actions\FindModels;
use Soyhuce\NextIdeHelper\Domain\Models\Actions\ResolveModelAttributes;
use Soyhuce\NextIdeHelper\Domain\Models\Actions\ResolveModelRelations;
use Soyhuce\NextIdeHelper\Domain\Models\Actions\ResolveModelScopes;
use Soyhuce\NextIdeHelper\Domain\Models\Actions\ResolveModelQueryBuilder;
use Soyhuce\NextIdeHelper\Domain\Models\Actions\ResolveModelCollection;
use Soyhuce\NextIdeHelper\Domain\Models\Actions\ResolveModelAttributesFromCasts;
use Soyhuce\NextIdeHelper\Domain\Models\Actions\ResolveModelAttributesFromAttributes;
use Soyhuce\NextIdeHelper\Domain\Models\Actions\ResolveModelAttributesFromGetters;
use Soyhuce\NextIdeHelper\Domain\Models\Collections\ModelCollection;
use Soyhuce\NextIdeHelper\Domain\Models\Entities\Attribute as SoyhuceAttribute;
use Composer\ClassMapGenerator\ClassMapGenerator;
use Illuminate\Support\Facades\Schema;
use \Illuminate\Database\SQLiteConnection;
use \Illuminate\Database\Connection;
use \Illuminate\Database\Schema\SQLiteBuilder;
use \Illuminate\Database\Schema\Blueprint;
use \Illuminate\Support\Fluent;

function injectMsyqlModifications(): void {
    \Illuminate\Database\Connection::resolverFor('sqlite', function ($connection, $database, $prefix, $config) {
        return new class($connection, $database, $prefix, $config) extends SQLiteConnection {

            public function getDefaultSchemaGrammar() {
                $grammarClass = new class extends SQLiteGrammar {
                    public function compileRenameIndex(Blueprint $blueprint, Fluent $command, $connection) {
                        try {
                            parent::compileRenameIndex($blueprint, $command, $connection);
                        }
                        catch (Exception $e) {
                            return [];
                        }
                    }
                };

                return (new $grammarClass())->setConnection($this);
            }

            public function runQueryCallback($query, $bindings, Closure $callback) {
                if (Str::startsWith($query, 'SET')) {
                    return null;
                }

                try {
                    return SQLiteConnection::runQueryCallback($query,$bindings, $callback);
                }
                catch (Exception $e) {
                    return null;
                }
            }

            public function getSchemaBuilder()
            {
                /** @var SQLiteConnection $this */
                if ($this->schemaGrammar === null) {
                    $this->useDefaultSchemaGrammar();
                }
                return new class($this) extends SQLiteBuilder {
                    protected function createBlueprint($table, Closure $callback = null)
                    {
                        return new class($table, $callback) extends Blueprint {
                            // This fixes the sqlite issues for multiple dropcolumns.
                            protected function ensureCommandsAreValid(Connection $connection) {
                                /** @var Blueprint $this */
                                if ($this->commandsNamed(['dropColumn'])->count() > 1) {
                                    // If they are the same we merge them.
                                    $first = null;
                                    /** @var $drop Fluent */
                                    foreach ($this->commandsNamed(['dropColumn']) as $drop) {
                                        if ($first === null) {
                                            $first = $drop;
                                        }
                                        else {
                                            $first['columns'] = [...$first->get('columns'), ...$drop->get('columns')];
                                        }
                                    }

                                    // Now remove them all except our first entry.
                                    $hadFirst = false;
                                    foreach ($this->commands as $key => $command) {
                                        if ($command->get('name') === 'dropColumn') {
                                            if (!$hadFirst) {
                                                $hadFirst = true;
                                            }
                                            else {
                                                unset($this->commands[$key]);
                                            }
                                        }
                                    }
                                }
                                return;
                            }
                            public function dropForeign($index)
                            {
                                return new Fluent();
                            }
                        };
                    }
                };
            }
        };
    });
}


function handle()
{
    Config::set('database.default', 'sqlite');
    Config::set('database.connections.sqlite.database', ':memory:');

    injectMsyqlModifications();

    // The logic below will take a mysql-schema if it exists and converts it into a much simpler structure.
    // This structure we can use to insert into the in-memory database so that we can parse the model information.
    if (file_exists(database_path('schema/mysql-schema.sql'))) {
        $parser = new iamcal\SQLParser();
        $parser->parse(file_get_contents(database_path('schema/mysql-schema.sql')));

        foreach ($parser->tables as $tableInfo) {
            Schema::create($tableInfo['name'], function (\Illuminate\Database\Schema\Blueprint $table) use ($tableInfo) {
                foreach ($tableInfo['fields'] as $field) {
                    $name = $field['name'];

                    if ($name === 'id') {
                        $table->id();
                    }

                    else {
                        $function = match($field['type']) {
                            "DATETIME" => "date",
                            "BIGINT", "INT" => "integer",
                            default => 'string'
                        };
                        $fieldI = $table->{$function}($name);

                        if ($field['null']) {
                            $fieldI->nullable();
                        }
                    }
                }
            });
        }
    }

    // Once we "normalized" the migration schema, we should be able to migrate directly into sqlite.
    Artisan::call('migrate');


    $detailData = [];

    // @todo expand this to be configurable.
    $modelsPath = base_path('app/Models');

    loadModels($modelsPath);

    $findModels = new FindModels();

    $models = new ModelCollection();
    $models = $models->merge($findModels->execute($modelsPath));

    foreach (modelResolvers($models) as $resolver) {
        foreach ($models as $model) {
            try {
                $resolver->execute($model);
            }
            catch(ArgumentCountError) {
            }
        }
    }

    $detailData = [];
    foreach ($models as $model) {
        $modelAttributes = [];
        $modelRelations = [];
        $modelScopes = [];

        foreach ($model->attributes as $attribute) {
            $modelAttributes[$attribute->name] = [
                'name' => $attribute->name,
                'type' => $attribute->type,
                /* 'cast' => $attribute->cast, */
                'magicMethods' => buildMagicMethodsForProperty($attribute),
            ];
        }

        foreach ($model->relations as $relation) {
            $relationInfo = $relation->eloquentRelation();
            $relationInfo->initRelation([$relation->parent->instance()], $relation->name);
            $defaultValue = $relation->parent->instance()->getRelation($relation->name);

            $modelRelations[$relation->name] = [
                'name' =>  $relation->name,
                'type' =>  get_class($relationInfo),
                'related' =>  $relation->related->fqcn,
                'isMany' => $defaultValue !== null,
                'property' => $relation->name
            ];
        }

        foreach ($model->scopes as $scope) {
            $modelScopes[$scope->name] = $scope->name;
        }

        $detailData[ltrim($model->fqcn, '\\')] = [
            'class' => $model->fqcn,
            'fileName' => $model->filePath,
            'relations' => $modelRelations,
            'attributes' => $modelAttributes,
            'scopes' => $modelScopes,
            'softDeletes' => $model->softDeletes()
        ];
    }

    echo json_encode($detailData);
}

function loadModels(string $modelsPath): void
{
    $dirs = glob($modelsPath, GLOB_ONLYDIR);
    $models = [];
    foreach ($dirs as $dirOrFile) {
        if (!is_dir($dirOrFile)) {
            continue;
        }

        if (file_exists($dirOrFile)) {
            $classMap = ClassMapGenerator::createMap($dirOrFile);

            // Sort list so it's stable across different environments
            ksort($classMap);

            foreach ($classMap as $model => $path) {
                $models[] = $model;
            }
        }
    }
}

function modelResolvers(ModelCollection $models): array
{
    return array_merge(
        [
            new ResolveModelAttributes(),
            new ResolveModelAttributesFromGetters(),
            new ResolveModelAttributesFromAttributes(),
            new ResolveModelAttributesFromCasts(),
            new ResolveModelCollection(),
            new ResolveModelQueryBuilder(),
            new ResolveModelScopes(),
            new ResolveModelRelations($models),
        ],
    );
}

function buildMagicMethodsForProperty(SoyhuceAttribute $attribute): array
{
    $attribute = [
        'where' . Str::studly($attribute->name) => [
            'type' => $attribute->type,
        ]
    ];

    return $attribute;
}

handle();
