<?php

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

function handle()
{
    $detailData = [];

    // @todo expand this to be configurable.
    $modelsPath = base_path('app/Models');

    loadModels($modelsPath);

    $findModels = new FindModels();

    $models = new ModelCollection();
    $models = $models->merge($findModels->execute($modelsPath));

    foreach (modelResolvers($models) as $resolver) {
        foreach ($models as $model) {
            $resolver->execute($model);
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
