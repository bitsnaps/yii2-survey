<?php
/**
 * Created by PhpStorm.
 * User: kozhevnikov
 * Date: 10/10/2017
 * Time: 13:59
 */

use onmotion\survey\models\SurveyUserAnswer;
use kartik\slider\Slider;
use vova07\imperavi\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var $question \onmotion\survey\models\SurveyQuestion */
/** @var $form \yii\widgets\ActiveForm */

echo Html::beginTag('div', ['class' => 'answers-stat']);

    $average = count($question->answers) > 0? $question->answers[0]->getTotalUserAnswersCount():0;
    $average = $average > 0 ? round($average, 1) : 0;
    echo "average <b>$average</b>";

echo Html::endTag('div');