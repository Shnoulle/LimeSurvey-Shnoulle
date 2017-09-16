<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
   * LimeSurvey
   * Copyright (C) 2013 The LimeSurvey Project Team / Carsten Schmitz
   * All rights reserved.
   * License: GNU/GPL License v2 or later, see LICENSE.php
   * LimeSurvey is free software. This version may have been modified pursuant
   * to the GNU General Public License, and as distributed it includes or
   * is derivative of works licensed under the GNU General Public License or
   * other free or open source software licenses.
   * See COPYRIGHT.php for copyright notices and details.
   *
     *	Files Purpose: lots of common functions
*/

class QuestionAttribute extends LSActiveRecord
{
    /**
     * Returns the static model of Settings table
     *
     * @static
     * @access public
     * @param string $class
     * @return CActiveRecord
     */
    public static function model($class = __CLASS__)
    {
        return parent::model($class);
    }

    /**
     * Returns the setting's table name to be used by the model
     *
     * @access public
     * @return string
     */
    public function tableName()
    {
        return '{{question_attributes}}';
    }

    /**
     * Returns the primary key of this table
     *
     * @access public
     * @return string
     */
    public function primaryKey()
    {
        return 'qaid';
    }

    /**
    * Defines the relations for this model
    *
    * @access public
    * @return array
    */
    public function relations()
    {
        return array(
            'qid' => array(self::BELONGS_TO, 'Question', 'qid', 'together' => true),
        );
    }

    /**
    * Returns this model's validation rules
    *
    */
    public function rules()
    {
        return array(
            array('qid,attribute','required'),
            array('value','LSYii_Validators'),
        );
    }

    /**
     * {@inheritdoc}
     * Reset silently readonly attributes
     */
    public function beforeSave() {
        if (!$this->isNewRecord) {
            $attributeDefinition = \QuestionHelper::getAttributesDefinitions();
            if(isset($attributeDefinition[$this->attribute]) && $attributeDefinition[$this->attribute]['readonly']) {
                $actualValue = self::model()->findByPk($this->qaid);
                $this->value = $actualValue->value;
            }
            if(isset($attributeDefinition[$this->attribute]) && $attributeDefinition[$this->attribute]['readonly_when_active']) {
                $oQuestion = Question::model()->find("qid=:qid",array('qid'=>$iQuestionID));
                if(Survey::model()->findByPk($oQuestion->sid)->isActive()) ) {
                    $this->value = $actualValue->value;
                }
            }
        }
        return parent::beforeSave();
    }

    public function setQuestionAttribute($iQuestionID,$sAttributeName, $sValue)
    {
        $oModel = new self;
        $aResult=$oModel->findAll('attribute=:attributeName and qid=:questionID',array(':attributeName'=>$sAttributeName,':questionID'=>$iQuestionID));
        if (!empty($aResult))
        {
            $oModel->updateAll(array('value'=>$sValue),'attribute=:attributeName and qid=:questionID',array(':attributeName'=>$sAttributeName,':questionID'=>$iQuestionID));
        }
        else
        {
            $oModel = new self;
            $oModel->attribute=$sAttributeName;
            $oModel->value=$sValue;
            $oModel->qid=$iQuestionID;
            $oModel->save();
        }
        return Yii::app()->db->createCommand()
            ->select()
            ->from($this->tableName())
            ->where(array('and', 'qid=:qid'))->bindParam(":qid", $qid)
            ->order('qaid asc')
            ->query();
    }

    /**
     * Set attributes for multiple questions
     *
     * NOTE: We can't use self::setQuestionAttribute() because it doesn't check for question types first.
     * TODO: the question type check should be done via rules, or via a call to a question method
     * TODO: use an array for POST values, like for a form submit So we could parse it from the controller instead of using $_POST directly here
     *
     * @var $iSid                   the sid to update  (only to check permission)
     * @var $aQidsAndLang           an array containing the list of primary keys for questions ( {qid, lang} )
     * @var $aAttributesToUpdate    array continaing the list of attributes to update
     * @var $aValidQuestionTypes    the question types we can update for those attributes
     */
    public function setMultiple($iSid, $aQidsAndLang, $aAttributesToUpdate, $aValidQuestionTypes)
    {
        if (Permission::model()->hasSurveyPermission($iSid, 'surveycontent','update'))  // Permissions check
        {
            // For each question
            foreach ($aQidsAndLang as $sQidAndLang)
            {
                $aQidAndLang  = explode(',', $sQidAndLang);                     // Each $aQidAndLang correspond to a question primary key, which is a pair {qid, lang}.
                $iQid         = $aQidAndLang[0];                                // Those pairs are generated by CGridView
                $sLanguage    = $aQidAndLang[1];

                // We need to generate a question object to check for the question type
                // So, we can also force the sid: we don't allow to update questions on different surveys at the same time (permission check is by survey)
                $oQuestion    = Question::model()->find('qid=:qid and language=:language and sid=:sid',array(":qid"=>$iQid,":language"=>$sLanguage, ":sid"=>$iSid));

                // For each attribute
                foreach($aAttributesToUpdate as $sAttribute)
                {
                    // TODO: use an array like for a form submit, so we can parse it from the controller instead of using $_POST directly here
                    $sValue         = Yii::app()->request->getPost($sAttribute);
                    $iInsertCount   = QuestionAttribute::model()->findAllByAttributes(array('attribute'=>$sAttribute, 'qid'=>$iQid));

                    // We check if we can update this attribute for this question type
                    // TODO: if (in_array($oQuestion->attributes, $sAttribute))
                    if (in_array($oQuestion->type, $aValidQuestionTypes))
                    {
                        if (count($iInsertCount)>0)
                        {
                            // Update
                            QuestionAttribute::model()->updateAll(array('value'=>$sValue),'attribute=:attribute AND qid=:qid', array(':attribute'=>$sAttribute, ':qid'=>$iQid));
                        }
                        else
                        {
                            // Create
                            $oAttribute            = new QuestionAttribute;
                            $oAttribute->qid       = $iQid;
                            $oAttribute->value     = $sValue;
                            $oAttribute->attribute = $sAttribute;
                            $oAttribute->save();
                        }
                    }
                }
            }
        }
    }

    /**
    * Returns Question attribute array name=>value
    *
    * @access public
    * @param int $iQuestionID
    * @param string $sLanguage restrict to this language (@todo : add it in qanda)
    * @return array
    */
    public function getQuestionAttributes($iQuestionID,$sLanguage=null)
    {

        $iQuestionID=(int)$iQuestionID;
        static $aQuestionAttributesStatic=array();// TODO : replace by Yii::app()->cache
        // Limit the size of the attribute cache due to memory usage
        $aQuestionAttributesStatic=array_splice($aQuestionAttributesStatic,-1000,null,true);
        if(isset($aQuestionAttributesStatic[$iQuestionID]))
        {
            return $aQuestionAttributesStatic[$iQuestionID];
        }
        $aQuestionAttributes=array();
        $oQuestion = Question::model()->find("qid=:qid",array('qid'=>$iQuestionID)); // Maybe take parent_qid attribute before this qid attribute
        if ($oQuestion)
        {
            if($sLanguage){
                $aLanguages = array($sLanguage);
            }else{
                $aLanguages = array_merge(array(Survey::model()->findByPk($oQuestion->sid)->language), Survey::model()->findByPk($oQuestion->sid)->additionalLanguages);
            }
            // Get all atribute set for this question
            $sType=$oQuestion->type;

            // For some reason this happened in bug #10684
            if ($sType == null)
            {
                throw new \CException("Question is corrupt: no type defined for question " . $iQuestionID);
            }

            $aAttributeNames = \ls\helpers\questionHelper::getQuestionAttributesSettings($sType);

            /* Get whole existing attribute for this question in an array*/
            $oAttributeValues = QuestionAttribute::model()->findAll("qid=:qid",array('qid'=>$iQuestionID));
            $aAttributeValues=array();
            foreach($oAttributeValues as $oAttributeValue)
            {
                if($oAttributeValue->language){
                    $aAttributeValues[$oAttributeValue->attribute][$oAttributeValue->language]=$oAttributeValue->value;
                }else{
                    /* Don't replace existing language, use '' for null key (and for empty string) */
                    $aAttributeValues[$oAttributeValue->attribute]['']=$oAttributeValue->value;
                }
            }
            // Fill with aQuestionAttributes with default attribute or with aAttributeValues
            // Can not use array_replace due to i18n
            foreach($aAttributeNames as $aAttribute)
            {

                if ($aAttribute['i18n'] == false)
                {
                    if(isset($aAttributeValues[$aAttribute['name']][''])){
                        $aQuestionAttributes[$aAttribute['name']]=$aAttributeValues[$aAttribute['name']][''];
                    }elseif(isset($aAttributeValues[$aAttribute['name']])){ /* Some survey have language is set for attribute without language (see #11980). This must fix for public survey and not only for admin. */
                        $aQuestionAttributes[$aAttribute['name']]=reset($aAttributeValues[$aAttribute['name']]);
                    }else{
                        $aQuestionAttributes[$aAttribute['name']]=$aAttribute['default'];
                    }
                }
                else
                {
                    foreach ($aLanguages as $sLanguage)
                    {
                        if (isset($aAttributeValues[$aAttribute['name']][$sLanguage])){
                            $aQuestionAttributes[$aAttribute['name']][$sLanguage] = $aAttributeValues[$aAttribute['name']][$sLanguage];
                        }elseif(isset($aAttributeValues[$aAttribute['name']][''])){
                            $aQuestionAttributes[$aAttribute['name']][$sLanguage] = $aAttributeValues[$aAttribute['name']][''];
                        }else{
                            $aQuestionAttributes[$aAttribute['name']][$sLanguage] = $aAttribute['default'];
                        }
                    }
                }
            }
        }
        else
        {
            return false; // return false but don't set $aQuestionAttributesStatic[$iQuestionID]
        }
        $aQuestionAttributesStatic[$iQuestionID]=$aQuestionAttributes;
        return $aQuestionAttributes;
    }

    public static function insertRecords($data)
    {
        $attrib = new self;
        foreach ($data as $k => $v)
            $attrib->$k = $v;
        return $attrib->save();
    }

    public function getQuestionsForStatistics($fields, $condition, $orderby=FALSE)
    {
        $command = Yii::app()->db->createCommand()
        ->select($fields)
        ->from($this->tableName())
        ->where($condition);
        if ($orderby != FALSE)
        {
            $command->order($orderby);
        }
        return $command->queryAll();
    }
}
?>
