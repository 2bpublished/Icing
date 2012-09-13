<?php
/**
  * Behavior for Versioning
  * 
  * Example Usage:
  *
  * @example 
  *   var $actsAs = array('Icing.Versioning');
  *
  * @example 
  	var $actsAs = array('Icing.Versioning' => array(
  		'contain' => array('Hour') //contains for relative model to be saved.
  		'limit' => '5' //how many version to save at any given time (false by default unlimited)
  	));
  
  * @version: since 1.0
  * @author: Nick Baker
  * @link: http://www.webtechnick.com
  */
App::uses('AuthComponent', 'Controller/Component');
class VersioningBehavior extends ModelBehavior {
	public $IcingVersion = null;
  /**
    * Setup the behavior
    */
  public function setUp(Model $Model, $settings = array()){
  	$settings = array_merge(array(
  		'contain' => array(),
  		'limit' => false
  	), (array)$settings);
  	if(!$Model->Behaviors->attached('Containable')){
  		$Model->Behaviors->attach('Containable');
  	}
  	$this->settings[$Model->alias] = $settings;
  	$this->IcingVersion = ClassRegistry::init('Icing.IcingVersion');
  }
 
  /**
    * On save will version the current state of the model with containable settings.
    * @param Model model
    */
  public function beforeSave(Model $Model){
  	$this->saveVersion($Model);
    return $Model->beforeSave();
  }
  
  /**
    * Version the delete, mark as deleted in versioning
    * @param Model model
    * @param boolean cascade
    */
  public function beforeDelete(Model $Model, $cascade){
  	$this->saveVersion($Model, $delete = true);
    return $Model->beforeDelete($cascade);
  }
  
  /**
  * Restore data from a version_id
  * @param int version id
  * @return result of saveAll on model
  */
  public function restoreVersion(Model $Model, $version_id){
  	$restore = $this->IcingVersion->findById($version_id);
  	if(!empty($restore)){
  		$model_data = json_decode($restore['IcingVersion']['json'], true);
  		return ClassRegistry::init($restore['IcingVersion']['model'])->saveAll($model_data);
  	}
  	return false;
  }
  
  /**
  * Get the version data from the Model based on settings and deleting
  * this is used in beforeDelete and beforeSave
  * @param Model model
  * @param boolean deleted
  */
  private function saveVersion(Model $Model, $delete = false){
  	$model_id = 0;
  	if($Model->id){
  		$model_id = $Model->id;
  	}
  	if(isset($Model->data[$Model->alias][$Model->primaryKey]) && !empty($Model->data[$Model->alias][$Model->primaryKey])){
  		$model_id = $Model->data[$Model->alias][$Model->primaryKey];
  	}
  	if($model_id){
  		$data = $Model->data; //cache the data incase the model has some afterfind stuff that sets data
  		$current_data = $Model->find('first', array(
  			'conditions' => array("{$Model->alias}.{$Model->primaryKey}" => $model_id),
  			'contain' => $this->settings[$Model->alias]['contain']
  		));
  		$version_data = array(
  			'user_id' => AuthComponent::user('id'),
  			'model_id' => $model_id,
  			'model' => $Model->alias,
  			'json' => json_encode($current_data),
  			'is_delete' => $delete
  		);
  		$Model->data = $data;
  		return $this->IcingVersion->saveVersion($version_data, $this->settings[$Model->alias]['limit']);
  	}
  	return false;
  }
}