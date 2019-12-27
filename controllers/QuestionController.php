<?php

namespace onmotion\survey\controllers;

use onmotion\survey\models\Survey;
use onmotion\survey\models\SurveyAnswer;
use onmotion\survey\models\SurveyQuestion;
use onmotion\survey\models\SurveyType;
use onmotion\survey\models\SurveyStat;
use onmotion\survey\models\SurveyUserAnswer;

use vova07\imperavi\actions\GetAction;
use yii\base\Event;
use yii\base\Model;
use yii\db\Expression;
use yii\db\Query;
use yii\db\QueryBuilder;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Default controller for the `survey` module
 */
class QuestionController extends Controller
{

    public function actions()
    {
        return [
            'images-get' => [
                'class' => 'vova07\imperavi\actions\GetImagesAction',
                'url' => $this->module->params['uploadsUrl'] . \Yii::$app->session->get('surveyUploadsSubpath', ''), // URL адрес папки где хранятся изображения.
                'path' => $this->module->params['uploadsPath'] . \Yii::$app->session->get('surveyUploadsSubpath', ''), // Или абсолютный путь к папке с изображениями.
                'options' => ['only' => ['*.jpg', '*.jpeg', '*.png', '*.gif', '*.ico']], // These options are by default.
            ],
            'image-upload' => [
                'class' => 'vova07\imperavi\actions\UploadFileAction',
                'url' => $this->module->params['uploadsUrl'] . \Yii::$app->session->get('surveyUploadsSubpath', ''), // URL адрес папки где хранятся изображения.
                'path' => $this->module->params['uploadsPath'] . \Yii::$app->session->get('surveyUploadsSubpath', ''), // Или абсолютный путь к папке с изображениями.
            ],
            'files-get' => [
                'class' => 'vova07\imperavi\actions\GetFilesAction',
                'url' => $this->module->params['uploadsUrl'] . \Yii::$app->session->get('surveyUploadsSubpath', ''), // URL адрес папки где хранятся изображения.
                'path' => $this->module->params['uploadsPath'] . \Yii::$app->session->get('surveyUploadsSubpath', ''), // Или абсолютный путь к папке с изображениями.
            ],
            'file-upload' => [
                'class' => 'vova07\imperavi\actions\UploadFileAction',
                'url' => $this->module->params['uploadsUrl'] . \Yii::$app->session->get('surveyUploadsSubpath', ''), // URL адрес папки где хранятся изображения.
                'path' => $this->module->params['uploadsPath'] . \Yii::$app->session->get('surveyUploadsSubpath', ''), // Или абсолютный путь к папке с изображениями.
                'uploadOnlyImage' => false, // Для загрузки не только изображений.
            ],

        ];
    }

    public function actionCreate($id)
    {
        $survey = $this->findSurveyModel($id);
        $question = new SurveyQuestion();
        $question->loadDefaultValues();
        // $question->survey_question_name = \Yii::t('survey', 'New Question');
        $survey->link('questions', $question);

        for ($i = 1; $i <= 2; ++$i) {
            $question->link('answers', (new SurveyAnswer(['survey_answer_sort' => $i])));
        }

        return $this->renderAjax('_form', ['question' => $question]);
    }

    public function actionDelete($id)
    {
        $question = $this->findModel($id);

        if ($question->delete()) {
            return '<span></span>';
        } else {
            throw new HttpException('500', 'unable to delete record');
        }
    }

    public function actionValidate($id)
    {
        $question = $this->findModel($id);
        $post = \Yii::$app->request->post();
        $action = ArrayHelper::getValue($post, "action");
        $result = [];
        \Yii::$app->response->format = Response::FORMAT_JSON;

        $questionIsChanged = false;

        $questionData = ArrayHelper::getValue($post, "SurveyQuestion.{$question->survey_question_id}");
        if (!empty($questionData) && $question->load($questionData, '')) {
            $isValid = $question->validate();
            $questionIsChanged = !empty($question->getDirtyAttributes());
            foreach ($question->getErrors() as $attribute => $errors) {
                $result["surveyquestion-{$question->survey_question_id}-{$attribute}"] = $errors;
            }
            if ($isValid) {
                $attrsForSave = $question->getAttributes(null, ['survey_question_type']);
                $attrs = array_keys($question->getDirtyAttributes(array_keys($attrsForSave)));
                $question->save(false, $attrs);
            }
        }

        $answersData = ArrayHelper::getValue($post, "SurveyAnswer.{$question->survey_question_id}");

        if (!empty($answersData)
            && (count($answersData) === count($question->answers))
            && Model::loadMultiple($question->answers, $answersData, '')) {
            foreach ($question->answers as $i => $model) {
                if (!$questionIsChanged || $action !== 'delete-answer') {
                    $model->validate();
                    foreach ($model->getErrors() as $attribute => $errors) {
                        $result["surveyanswer-{$question->survey_question_id}-{$i}-{$attribute}"] = $errors;
                    }
                }
                //    $model->validate();
                $model->save();
            }
        }

        return $result;
    }

    public function actionUpdate($id)
    {
        $question = $this->findModel($id);

        $post = \Yii::$app->request->post();

        $questionData = ArrayHelper::getValue($post, "SurveyQuestion.{$question->survey_question_id}");

        $isTypeChanged = false;
        if (!empty($questionData) && $question->load($questionData, '') && $question->validate()) {
            $isTypeChanged = $question->isAttributeChanged('survey_question_type');
            if ($isTypeChanged) {
                $question->changeDefaultValuesOnTypeChange();
            }
            $question->save(false);
        }

        $answersData = ArrayHelper::getValue($post, "SurveyAnswer.{$question->survey_question_id}");
        if (!empty($answersData) && Model::loadMultiple($question->answers, $answersData, '') && !$isTypeChanged) {
            foreach ($question->answers as $answer) {
                $answer->save();
            }
        }

        $question->refresh();

        return $this->renderAjax('_form', ['question' => $question]);
    }

    public function actionEdit($id)
    {
        $question = $this->findModel($id);
        return $this->renderAjax('_form', ['question' => $question]);
    }


    public function actionAddAnswer($id, $after = null)
    {
        $question = $this->findModel($id);
        $answers = $question->answers;
        $lastAnswer = array_values(array_slice($answers, -1))[0];

        if (!isset($after)) {
            $sort = ArrayHelper::getValue($lastAnswer, 'survey_answer_sort', 0) + 1;
        } else {
            $pevAnswer = $answers[$after] ? $answers[$after] : null;
            $sort = ArrayHelper::getValue($pevAnswer, 'survey_answer_sort', 0) + 1;

            //moving all new answers forward
            SurveyAnswer::updateAll(['survey_answer_sort' => new Expression('survey_answer_sort+1')],
                ['AND', ['>=', 'survey_answer_sort', $sort], ['survey_answer_question_id' => $question->survey_question_id]]);
        }

        $question->link('answers', (new SurveyAnswer(['survey_answer_sort' => $sort])));
        //updated model
        $question = $this->findModel($id);

        return $this->renderAjax('_form', ['question' => $question]);
    }

    public function actionDeleteAnswer($id, $answer)
    {
        $question = $this->findModel($id);
        $answer = $question->answers[$answer];
        $answer->delete();

        //updated model
        $question = $this->findModel($id);

        return $this->renderAjax('_form', ['question' => $question]);
    }

    public function actionUpdateAndClose($id)
    {
        $question = $this->findModel($id);

        $post = \Yii::$app->request->post();

        $questionData = ArrayHelper::getValue($post, "SurveyQuestion.{$question->survey_question_id}");
        if (!empty($questionData) && $question->load($questionData, '')) {
            $question->save();
        }

        $answersData = ArrayHelper::getValue($post, "SurveyAnswer.{$question->survey_question_id}");
        if (!empty($answersData) && Model::loadMultiple($question->answers, $answersData, '')) {
            foreach ($question->answers as $answer) {
                $answer->save();
            }
        }

        return $this->renderAjax('cardView', ['question' => $question]);
    }


    protected function findModel($id)
    {
        if (($model = SurveyQuestion::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    protected function findSurveyModel($id)
    {
        if (($model = Survey::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    protected function findTypeModel($id)
    {
        if (($model = SurveyType::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
    
    public function actionSubmitAnswer($id, $n)
    {
        $question = $this->findModel($id);
        $this->validate($question);

        return $this->renderAjax('@surveyRoot/views/widget/question/_form', 
                ['question' => $question, 'number' => $n]);
    }

    /**
     * @param $question SurveyQuestion
     * @return array|bool
     */
    protected function validate(&$question)
    {

        $stat = SurveyStat::getAssignedUserStat(\Yii::$app->user->getId(), $question->survey->survey_id);
        //do not work with completed surveys
        if ($stat->survey_stat_is_done) {
            return false;
        }
        $post = \Yii::$app->request->post();

        $result = [];

        \Yii::$app->response->format = Response::FORMAT_JSON;

        $answersData = ArrayHelper::getValue($post, "SurveyUserAnswer.{$question->survey_question_id}");
        $userAnswers = $question->userAnswers;

        if (!empty($answersData)) {
            if ($question->survey_question_type === SurveyType::TYPE_MULTIPLE
                || $question->survey_question_type === SurveyType::TYPE_RANKING
                || $question->survey_question_type === SurveyType::TYPE_MULTIPLE_TEXTBOX
                || $question->survey_question_type === SurveyType::TYPE_DATE_TIME
                || $question->survey_question_type === SurveyType::TYPE_CALENDAR
            ) {
                foreach ($question->answers as $i => $answer) {
                    $userAnswer = isset($userAnswers[$answer->survey_answer_id]) ? $userAnswers[$answer->survey_answer_id] : (new SurveyUserAnswer([
                        'survey_user_answer_user_id' => \Yii::$app->user->getId(),
                        'survey_user_answer_survey_id' => $question->survey_question_survey_id,
                        'survey_user_answer_question_id' => $question->survey_question_id,
                        'survey_user_answer_answer_id' => $answer->survey_answer_id
                    ]));
                    if ($userAnswer->load($answersData[$answer->survey_answer_id], '')) {
                        $userAnswer->validate();
                        foreach ($userAnswer->getErrors() as $attribute => $errors) {
                            $result["surveyuseranswer-{$question->survey_question_id}-{$answer->survey_answer_id}-{$attribute}"] = $errors;
                        }
                        $userAnswer->save();
                    }
                }
                $question->refresh();
                $question->validateMultipleAnswer();
                foreach ($question->userAnswers as $userAnswer) {
                    foreach ($userAnswer->getErrors() as $attribute => $errors) {
                        $result["surveyuseranswer-{$question->survey_question_id}-{$userAnswer->survey_user_answer_answer_id}-{$attribute}"] = $errors;
                    }
                }
            } elseif ($question->survey_question_type === SurveyType::TYPE_ONE_OF_LIST
                || $question->survey_question_type === SurveyType::TYPE_DROPDOWN
                || $question->survey_question_type === SurveyType::TYPE_SLIDER
                || $question->survey_question_type === SurveyType::TYPE_SINGLE_TEXTBOX
                || $question->survey_question_type === SurveyType::TYPE_COMMENT_BOX
            ) {
                $userAnswer = !empty(current($userAnswers)) ? current($userAnswers) : (new SurveyUserAnswer([
                    'survey_user_answer_user_id' => \Yii::$app->user->getId(),
                    'survey_user_answer_survey_id' => $question->survey_question_survey_id,
                    'survey_user_answer_question_id' => $question->survey_question_id,
                ]));
                if ($userAnswer->load($answersData, '')) {
                    $userAnswer->validate();
                    foreach ($userAnswer->getErrors() as $attribute => $errors) {
                        $result["surveyuseranswer-{$question->survey_question_id}-{$attribute}"] = $errors;
                    }
                        $userAnswer->save();
                }
            }
        }

        return $result;
    }

    
}
