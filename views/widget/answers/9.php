<?php
/**
 * Created by PhpStorm.
 * User: kozhevnikov
 * Date: 10/10/2017
 * Time: 13:59
 */

use onmotion\survey\models\SurveyUserAnswer;
use kartik\date\DatePicker;
use kartik\datetime\DateTimePicker;
use vova07\imperavi\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var $question \onmotion\survey\models\SurveyQuestion */
/** @var $form \yii\widgets\ActiveForm */

$userAnswers = $question->userAnswers;

if (count($question->answers) == 0){
    echo Html::tag('div', '<p>Cannot found answer value name!</p>', ['class' => 'alert alert-danger']);
    /*$userAnswer = !empty(current($userAnswers)) ? current($userAnswers) : (new SurveyUserAnswer()) ;
    echo $form->field($userAnswer, "[$question->survey_question_id]survey_user_answer_value")
            ->widget(DatePicker::classname(), [
        'name' => $userAnswer,
        'options' => ['placeholder' => 'Enter event time ...'],
        'removeButton' => false,
        'pluginOptions' => [
            'format' => 'dd-MM-yyyy',
            'autoclose' => true
        ]
    ])->label(\Yii::t('survey', 'Answer'));*/
    echo Html::tag('div', '', ['class' => 'clearfix']);
    
} else {
    foreach ($question->answers as $i => $answer) {
    //    $userAnswer = $userAnswers[$answer->survey_answer_id] ?? (new SurveyUserAnswer()); //PHP7
        $userAnswer = isset($userAnswers[$answer->survey_answer_id])? $userAnswers[$answer->survey_answer_id] : (new SurveyUserAnswer());

        echo $form->field($userAnswer, "[$question->survey_question_id][$answer->survey_answer_id]survey_user_answer_value",
            [
                'template' => "<div class='survey-questions-form-field date-form-field'>{input}{label}\n{hint}\n{error}</div>",
                'labelOptions' => ['class' => 'css-label answer-text'],
            ]
        )->widget(DatePicker::classname(), [
            'options' => ['placeholder' => 'Enter event time ...'],
            'removeButton' => false,
            'pluginOptions' => [
                'format' => 'dd-MM-yyyy',
                'autoclose' => true
            ]
        ])->label($answer->survey_answer_name);
        // echo Html::label($answer->survey_answer_name);

        echo Html::tag('div', '', ['class' => 'clearfix']);
    }
}
//echo "<h1>Answers: ".count($question->answers)."</h1>";
//echo "<h1>User Answers: ".count($question->userAnswers)."</h1>";