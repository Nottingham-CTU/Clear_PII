# Clear Participant Identifier Information (PII)

This REDCap module clears participant identifier information (PII) either automatically on passing logic 
or by manually selecting a record.

Please note that this module is not intended to be used with repeating instruments or events. All
fields used for Participant Identifier variables should not be on a repeating instance or event.

## Project-level configuration options

This module provides project-level configuration options that can be modified by administrators ONLY. 
Note: settings can be imported by all users with 'Project Design and Setup' rights.

### Settings

* Roles with access to manually remove data from specified PIIs, enter 1 role name per line or * for all roles
* Participant Identifier variables to blank (must have at least one PII variable)
    - Event
    - Field
* The logic will only RUN when record is saved or in the cron job that uses 'today' or 'now' inside datediff(). If now/today is not used within the datediff, the logic will only when the record is saved. 


## Accessing Clear PIIs Manually

If your role is configured to have access, the link to the Clear PIIs plugin pages will appear under the external modules heading once
the module has been enabled in a project. 
This will enable the user to manually remove data from PIIs fields configured.


## Clear PIIs Manually

On the main page:
    * a list of PIIs fields configured in the external module that can have data removed/cleared (at least one field must be selected).
    * an text box to enter record id (the record must exist and from the PII fields selected for the record, at least one field must have data to clear)
    * a 'Clear Record' button

Once the clear record button is clicked and initial checks are done, a table with PII field values for the record will be displayed to give the user the option to:
   * Continue
   * Cancel

If the data fails to clear the failure is logged in the REDCap project logging.

### Clear PIIs Cron Jobs

There is one cron jobs in the module.

* clearPIIViaCron - Check logic in Clear PIIs settings that uses 'today' or 'now' inside datediff() function to clear specified PII fields- cron is called every 4 hours

Note: For testing purposes the 'clearPIIViaCron' cron job, 'cron_frequency' (in seconds) can be updated run more frequently. The module needs to be restarted for the module to use the new value. 


