<?php

namespace Nottingham\ClearPII;
/**
 * 	Clear participant record fields manually.
 */
$continue = false;
$record = '';
$fieldsSelected = array();
$status = $_GET['status'];
$message = $_GET['message'];
$piiEvents = $module->getProjectSetting('pi-event', $project_id);
$piiFields = $module->getProjectSetting('pi-field', $project_id);

// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Handle form submissions.
if (!empty($_POST) && isset($_POST['action'])) {  
    // records have been selected, check if their is PII field data that can be cleared
    // if there is data to be cleared, put up values so user can decide whether to continue or not
    if ($_POST['action'] == 'select_record') {

        $continue=true;
        $record = htmlspecialchars($_POST['record_id'], ENT_QUOTES );
        $fieldsSelected = explode(",", $_POST['selected_fields']);
        
        $data = \REDCap::getData( 'array', $record);
        if($data == null)
        {
            $module->alert("Record cannot be found in project.");
            $continue=false;
        }
        else
        {
            $clear_data = false;
            for ( $i = 0; $i < count($fieldsSelected); $i++ )
            {
                if($data[$record][$piiEvents[$fieldsSelected[$i]]][$piiFields[$fieldsSelected[$i]]] != '')
                {
                    $clear_data = true;
                    break;
                }  
            }
            if($clear_data === false)
            {
                $module->alert("There is no field data to be cleared for this record.");
                $continue=false;
            }
        }
        
    }
    // clear pii fields for record that has data that needs to cleared
    else if ($_POST['action'] == 'clear_record') {
        $record = htmlspecialchars($_POST['record_id'], ENT_QUOTES );
        $fieldsSelected = explode(",", $_POST['selected_fields']);
        if($module->clearPII($project_id, $record, 'on manual selection.', $fieldsSelected))
        {
            $module->alert("Successfully cleared PII fields for record, ".$record.".");
            $record = '';
            $fieldsSelected = array();
        }
        else {
            $module->alert("Failed cleared PII fields for record, ".$record.".");
            $continue=false;
        }
    } 
}


// checks if the field is selected
function isFieldSelected($value,  $selected)
{
    for ( $i = 0; $i < count($selected); $i++ )
    {
        if($selected[$i] == $value)
        {
            return true;
        }
    }
    return false;
}

global $Proj;





// Define page style.
$style = '
	table.dataTable thead tr th {
		background-color: #FFFFE0;
		border-top: 1px solid #aaaaaa;
		border-bottom: 1px solid #aaaaaa;
	}
	table.dataTable.cell-border thead tr th {
		border-right: 1px solid #ddd;
	}
	table.dataTable.cell-border thead tr th:first-child {
		border-left: 1px solid #ddd;
	}
	table.dataTable tr td a.rl { font-size:8pt;font-family:Verdana;text-decoration:underline; }
	table.dataTable tr th { line-height: 11px; }
	table.dataTable tr th.rpthdrc { border-top:0; }
	table.dataTable tr th.rptchclbl { border-bottom:1px dashed #ccc; }
	table.dataTable tbody td, table.dataTable thead th { padding:5px; }
	table.dataTable tbody tr:nth-child(2n) { background-color: #eee !important; }
	table.dataTable tbody tr:nth-chlid(2n+1) { background-color: #fcfef5 !important; }
	';
echo '<script type="text/javascript">',
	 '(function (){var el = document.createElement(\'style\');',
	 'el.setAttribute(\'type\',\'text/css\');',
	 'el.innerText = \'', addslashes( preg_replace( "/[\t\r\n ]+/", ' ', $style ) ), '\';',
	 'document.getElementsByTagName(\'head\')[0].appendChild(el)})()</script>';

?>



<div class="projhdr"><i class="far fa-list-alt"></i> Clear Participant Identifier Information (PII)</div>
<a href="<?php echo $module->getUrl( 'README.md' );?>" target="_blank"><i class="fas fa-book fs11"></i> View Documentation</a>

<form method="post" id="clear-record-frm">
 <input type="hidden" name="action" id="action" value="">
 <input type='hidden' name="selected_fields" id="selected_fields"  value="">
 <p>Please select fields with participant identifier information that you wish to clear.</p>
 <table id="pii-fields" class="dataTable cell-border no-footer">
  <thead style="position:sticky;top:0px">
   <tr>
    <th>Field</th>
    <th>Clear Field</th>
   </tr>
  </thead>
  <tbody>
<?php

for ( $i = 0; $i < count($piiEvents); $i++ )
{  
?>
   <tr>
    <td style="text-align:left"><?php echo htmlspecialchars($Proj->metadata[$piiFields[$i]]['element_label']. ' (['.$Proj->getUniqueEventNames($piiEvents[$i]).']['.$piiFields[$i].'])', ENT_QUOTES )?></td>
    <td>&nbsp;<input type="checkbox"
                     name="clear_field[]" <?php if(isFieldSelected($i, $fieldsSelected)) echo "checked"; ?> <?php if($continue) echo "disabled"; ?> value="<?php echo htmlspecialchars($i, ENT_QUOTES ); ?>"></td>
<?php
}
?>
    </tr>
  </tbody>
 </table><br>
 <p>Enter a participant that has changed trial status to remove participant identifier information e.g. discontinued/withdrawn.</p>
 <p>
  Record Id:&nbsp;&nbsp; <input type="text" <?php if($continue) echo "disabled"; ?> value="<?php echo htmlspecialchars($record, ENT_QUOTES );?>" id="clear-record" name="clearrecord"> <br><br>  
  <input type="hidden" name="csrf_token" value="<?php echo \System::getCsrfToken(); ?>">
  <input type='hidden' id='record_id' name='record_id' value='<?php echo htmlspecialchars($record, ENT_QUOTES );?>'>
  <button id="review-record-button" class="jqbuttonmed ui-button ui-corner-all ui-widget"
          onclick="reviewRecordFields(this.form.clearrecord.value);return false" style="<?php if($continue) echo "display:none"; ?>">
   <span style="vertical-align:middle;color:#A00000"><i class="fas fa-trash-alt"></i> Clear Record</span>
  </button>
<?php
  if($continue)
  {
?>
  <div id="pii-fields-cleared">
  <p>List of values from selected fields with participant identifier information that with be cleared for record, <b><?php echo htmlspecialchars($record, ENT_QUOTES );?></b>.</p>
  <table class="dataTable cell-border no-footer">
  <thead style="position:sticky;top:0px">
   <tr>
    <th>Field to clear</th>
    <th>Value</th>
   </tr>
  </thead>
  <tbody>
<?php

for ( $i = 0; $i < count($fieldsSelected); $i++ )
{
    if($data[$record][$piiEvents[$fieldsSelected[$i]]][$piiFields[$fieldsSelected[$i]]] != '')
    {
?>
    <tr>
     <td style="text-align:left"><?php echo htmlspecialchars($Proj->metadata[$piiFields[$fieldsSelected[$i]]]['element_label']. ' (['.$Proj->getUniqueEventNames($piiEvents[$fieldsSelected[$i]]).']['.$piiFields[$fieldsSelected[$i]].'])', ENT_QUOTES )?></td>
     <td style="text-align:left"><?php echo htmlspecialchars($data[$record][$piiEvents[$fieldsSelected[$i]]][$piiFields[$fieldsSelected[$i]]], ENT_QUOTES )?></td>
<?php
    }
}
?>
    </tr>
  </tbody>
  </table></div><br>
   <div>
   <button id="clear-record-button"  style="width:100px;background-color:green;" class="jqbuttonmed ui-button ui-corner-all ui-widget" onclick="clearRecordFields(this.form.clearrecord.value);return false">
   <span style="vertical-align:middle;color:white"><i class="fas fa-trash-alt"></i> Continue</span>
   </button>&nbsp;&nbsp;
   <button id="cancel-clear" style="width:100px;background-color:green;" class="jqbuttonmed ui-button ui-corner-all ui-widget" onclick="cancelClear();return false">
   <span style="vertical-align:middle;color:white;">Cancel</span>
   </button>
   <div>
<?php
  }
 
?>
 </p>
</form>
<script type="text/javascript">
    
  // called to clear data, checks field is slected and record has been entered
  function reviewRecordFields(record)
  {
     var vPIIFieldsSelected = $('#pii-fields input[type=checkbox]:checked');
      
      $("#record_id").val(record);
      $("#action").val('select_record');
   
      if(vPIIFieldsSelected.length < 1)
      {
         alert("At least one field must be selected.") ;
         return false;
      }
      if(record.trim() == "")
      {
         alert("A record id must be entered.") ;
         return false;
      }
      

      selectedValues = '';
      for ( var i = 0; i < vPIIFieldsSelected.length; i++ )
      {
          if(selectedValues != '')
          {
              selectedValues += ',';
          }
         selectedValues += vPIIFieldsSelected[i].value;   
      }

      $("#selected_fields").val(selectedValues);
      $("#clear-record-frm").submit();
  
     
  }
  
  // called if the user wishes to clear the PII field data, once values are returned
  function clearRecordFields(record)
  {
      var vPIIFieldsSelected = $('#pii-fields input[type=checkbox]:checked');
      $("#record_id").val(record);
      $("#action").val('clear_record');
   
      selectedValues = '';
      for ( var i = 0; i < vPIIFieldsSelected.length; i++ )
      {
          if(selectedValues != '')
          {
              selectedValues += ',';
          }
         selectedValues += vPIIFieldsSelected[i].value;   
      }

      $("#selected_fields").val(selectedValues);
      $("#clear-record-frm").submit();
  }
  
  // called if the cancel button is clicked
  function cancelClear()
  {
      var vPIIFields = $('#pii-fields input[type=checkbox]');
    
      for ( var i = 0; i < vPIIFields.length; i++ )
      {
         vPIIFields[i].disabled = false;
      }
   
      var vRecord = $('#clear-record');
      vRecord[0].disabled = false;
      
      var vButton = $('#review-record-button');
      vButton.css('display', '')
      
      var vTableDiv = $('#pii-fields-cleared');
      vTableDiv.remove();
      vButton = $('#clear-record-button');
      vButton.remove();
      vButton = $('#cancel-clear');
      vButton.remove();
      
      
  }
</script>

<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

