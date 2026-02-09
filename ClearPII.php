<?php

namespace Nottingham\ClearPII;

class ClearPII extends \ExternalModules\AbstractExternalModule {

    // Show the link based on whether the user has no access, hide the link.
    function redcap_module_link_check_display($project_id, $link) {
       
        if ($this->isAccessibleToRole($project_id)) {
            return $link;
        }

        return null;
    }
    

    // Check if the role has access.
    function isAccessibleToRole($project_id) {
        $role_access = $this->getProjectSetting( 'clear-pii-user-roles', $project_id );
        // Check each allowed role and allow access if the user has the role.
        foreach ( explode( "\n", $role_access) as $role )
        {
            $role = trim( $role );
            
            if ( $role === '*' || $role === $this->getUserRole() || $this->getUser()->isSuperUser())
            {
                return true;
            }
        } 
        // Don't allow access .
        return false;
    }
    
    // Get the role name of the current user.
    function getUserRole()
    {
        $userRights = $this->getUser()->getRights();
        if ( $userRights === null )
        {
                return null;
        }
        if ( $userRights[ 'role_id' ] === null )
        {
                return null;
        }
        return $userRights[ 'role_name' ];
    }

    


    // Function called by the CRON to check any pii fields need to be cleared for discontinued/withdrawn participants with datediff+today/now
    public function clearPIIViaCron() {

        foreach ($this->getProjectsWithModuleEnabled() as $project_id) 
        { 
            $logic = $this->getProjectSetting( 'clear-pii-logic', $project_id );
            
            // only if check if logic is with datediff+today/now
            if($logic !== ''  && ((stripos($logic, 'datediff') !== false 
                        && stripos($logic, 'now') !== false) ||  (stripos($logic, 'datediff') !== false 
                        && stripos($logic, 'today') !== false))) 
            {
               
                 // get a list of events and fields that need to be cleared
                $pii_events = $this->getProjectSetting('pi-event', $project_id);
                $pii_fields = $this->getProjectSetting('pi-field', $project_id);

                $record_data = \REDCap::getData($project_id, 'array', null, $pii_fields, $pii_events);

                foreach (array_keys($record_data) as $record)
                {
                    // check if there is PII field data that needs to be cleared
                   $bSave = false;
                   for ( $i = 0; $i < count($pii_events); $i++ )
                   {
                        if($pii_events[$i] != '' && $pii_fields[$i] != '' && $record_data[$record][$pii_events[$i]][$pii_fields[$i]] != '')
                       {
                           $bSave = true;
                           break;
                       }
                   }

                   // if there is PII field data, check logic and if passes logic, clear fields
                   if($bSave)
                   {
                        $passedLogic = \REDCap::evaluateLogic($logic, $project_id, $record);
                        if($passedLogic)
                        {
                            $this->clearPII($project_id, $record, 'in cron job', array(), $record_data);
                        }
                   }
                   
                       
                }
            }
        }
    }
    
    public function alert($msg) {
        echo "<script type='text/javascript'>alert('".htmlspecialchars($msg, ENT_QUOTES )."');</script>";
    }

    // run logic, if configured & triggered by save to clear .
    function redcap_save_record( $project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {  
        $logic = $this->getProjectSetting( 'clear-pii-logic', $project_id );
         // Has conditional logic?
        $triggerOnLogic = ($logic != '');
        
         
        // Trigger it based on logic?
        if ($triggerOnLogic) {
            
            
            // Get data for this record
            global $Proj;

            // Is this a repeating form/event?
            $isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($event_id, $instrument);
            $repeat_instrument = $Proj->isRepeatingForm($event_id, $instrument) ? $instrument : '';
            
            if ($isRepeatingFormOrEvent) {
                $passedLogicTest = \REDCap::evaluateLogic($logic, $project_id, $record, $event_id, $repeat_instance, $instrument, $instrument);
            } else {
                $passedLogicTest = \REDCap::evaluateLogic($logic, $project_id, $record, $event_id, 1, "", $instrument);
            }
            
             // If passed logic, clear fields
            if ($passedLogicTest)
            {
                $this->clearPII($project_id, $record, 'on save.');
            }
        }
                 
    }
    
    function clearPII($project_id, $record, $type, $fields = array(), $data = array())
    {
        
        $inputData = array();

        // get a list of events and fields that need to be cleared
        $pii_events = $this->getProjectSetting('pi-event', $project_id);
        $pii_fields = $this->getProjectSetting('pi-field', $project_id);
        $field_info = \REDCap::getDataDictionary( $project_id, 'array', false, $pii_fields);
    
        // only bother to get record data if not passed 
        if(count($data) === 0)
        {
            $data = \REDCap::getData($project_id, 'array', $record, $pii_fields, $pii_events);
        }
           
        $nSaveCount = 0;
        
        if(count($fields) > 0)
        {
            // called from manual clear incase not all PII fields are checked to check if data needs to be cleared
            for ( $i = 0; $i < count($fields); $i++ )
            {
                if($data[$record][$pii_events[$fields[$i]]][$pii_fields[$fields[$i]]] != '')
                { 
                    if($field_info[$pii_fields[$fields[$i]]]['field_type'] == 'file')
                    {
                        $docIds[] = $data[$record][$pii_events[$fields[$i]]][$pii_fields[$fields[$i]]];
                    }
                    $inputData[$record][$pii_events[$fields[$i]]][$pii_fields[$fields[$i]]] = '';
                    $nSaveCount++;
                }  
            }
        }
        else
        {
            // goes through list events and fields configured in EM settings to check if data needs to be cleared
            for ( $i = 0; $i < count($pii_events); $i++ )
            {
                if($pii_events[$i] != '' && $pii_fields[$i] != '' && $data[$record][$pii_events[$i]][$pii_fields[$i]] != '')
                {
                    if($field_info[$pii_fields[$i]]['field_type'] == 'file')
                    {
                        $docIds[] = $data[$record][$pii_events[$i]][$pii_fields[$i]];
                    }  
                    $inputData[$record][$pii_events[$i]][$pii_fields[$i]] = '';
                    $nSaveCount++;
                }
            }
        }

        if($nSaveCount > 0)
        {
            //clear PII fields and log 
            $params = array('project_id'=>$project_id, 'dataFormat'=>'array', 'data'=>$inputData, 'overwriteBehavior'=>'overwrite','type'=>'flat', 'skipFileUploadFields'=>false);
	    $saveData = \REDCap::saveData($params);
            if($saveData['item_count'] ===  $nSaveCount)
            {
                // delete any files if file upload or signature fields.
                $file_info = "";
                if($docIds !== null)
                {
                    for ( $i = 0; $i < count($docIds); $i++ )
                    {
                        if(\Files::deleteFileByDocId($docIds[$i], $project_id) == false)
                        {
                            $file_info .= "\nFailed to delete file(Doc Id=".$docIds[$i].")";
                        }
                        else {
                            $file_info .= "\nRemoved file (Doc Id=".$docIds[$i].")";
                        }
                    }
                }
                \REDCap::logEvent('Clear PII', "Clearing fields ".$type.$file_info, "Clearing fields ".$type, $record, "", $project_id);
                return true;
            }
            else
            {
                \REDCap::logEvent('Clear PII', "Failed to clear fields ".$type."\nErrors=".implode("\n",$saveData['errors']), "Clearing fields ".$type, $record, "", $project_id);
                
            }
        }
        return false;
    }

    function validateSettings($settings) 
    {
        $errMsg = "";
        
        for ( $i = 0; $i < count( $settings['pi-vars'] ); $i++ )
        {
            if($settings['pi-event'][$i] == '' || $settings['pi-field'][$i] == '')
            {
                $errMsg .= "Participant Identifier variable Event or Field " . ($i+1) . " is missing\n";
            }
            
            // add validation for repeating forms
            $form_name= \REDCap::getDataDictionary(PROJECT_ID,'array',false,$settings['pi-field'][$i])[$settings['pi-field'][$i]]['form_name'];
            global $Proj;
            $isRepeating = $Proj->isRepeatingFormOrEvent($settings['pi-event'][$i], $form_name);
            if($isRepeating)
            {
                $errMsg .=  "Participant Identifier variable Event or Field " . ($i+1) . " cannot be on repeating form or event\n";  
            }
                
        }
        
        if(!$this->getUser()->isSuperUser())
        {
            $errMsg .= "Only REDCap administartors can modify settings.\n";
        }
                
        if ($settings['clear-pii-logic'] != "") 
        {
            $logic = $settings['clear-pii-logic'];
            // Clean
            $logic = trim(html_entity_decode($logic, ENT_QUOTES));

            // Check if calculation is valid
            $logic = \Piping::pipeSpecialTags($logic, PROJECT_ID, null, null, null, USERID, true, null, null, false, false, false, true);

            // Obtain array of error fields that are not real fields
            $error_fields = \Design::validateBranchingCalc($logic);

	
            // Return list of fields that do not exist (i.e. were entered incorrectly), else continue.
            if (!empty($error_fields))
            {
                $errMsg .= "The following fields listed in your logic do not exist in this project and thus cannot be used. These fields must be removed\n - ".implode("\n - ", $error_fields);

            }
            else
            {
                $logicIsValid = \LogicTester::isValid($logic);
                if(!$logicIsValid)
                {
                    $errMsg .= "Logic is invalid";
                }
            }
            
           

        }
       

        if ($errMsg !== '') {
            return $errMsg;
        }
        return null;
    }

}
