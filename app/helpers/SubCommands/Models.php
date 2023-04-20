<?php

use Illuminate\Support\Str;
use Spatie\ModelInfo\Attributes\Attribute;
use Spatie\ModelInfo\ModelInfo;
use Composer\ClassMapGenerator\ClassMapGenerator;

function handle()
{
    $detailData = [];

    // @todo expand this.
    $modelsPath = base_path('app/Models');

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

    foreach ($models as $model) {
        try {
            $modelInfo = ModelInfo::forModel($model);
        } catch (\Error $e) {
            continue;
        } catch (\Exception $e) {
            continue;
        }

        if ($modelInfo) {
            $modelAttributes = [];
            $modelRelations = [];

            foreach ($modelInfo->attributes as $attribute) {
                $modelAttributes[$attribute->name] = [
                    'name' => $attribute->name,
                    'type' => $attribute->phpType,
                    'cast' => $attribute->cast,
                    'magicMethods' => buildMagicMethodsForProperty($attribute),
                ];
            }

            foreach ($modelInfo->relations as $relation) {
                $modelRelations[$relation->name] = [
                    'name' =>  $relation->name,
                    'type' =>  $relation->type,
                    'related' =>  $relation->related,
                    'property' => $relation->name
                ];
            }


            $detailData[$model] = [
                'class' => $modelInfo->class,
                'fileName' => $modelInfo->fileName,
                'tabelName' => $modelInfo->tableName,
                'relations' => $modelRelations,
                'attributes' => $modelAttributes,
            ];
        }
    }

    echo json_encode($detailData);
}

function buildMagicMethodsForProperty(Attribute $attribute): array
{
    $attribute = [
        'where' . Str::studly($attribute->name) => [
            'type' => $attribute->phpType,
            'cast' => $attribute->cast,
        ]
    ];

    return $attribute;
}

handle();
