### Monitoring QR ###

The Monitoring QR module leverages the Data Resolution Workflow to support a monitoring workflow that can be kept
independent of the main data resolution workflow.

This module inserts code into the `Hooks.php` and `DataEntry.php` REDCap files when the module is enabled at a system
level. The code is removed when the module is disabled.

#### System set up ####

Enabling the module at a system level will AUTOMATICALLY do the following via the system hook
`redcap_module_system_enable`;

1. Create the `GetMonitorQueries` stored procedure in the REDCap database. This procedure is required to provide the log 
of monitor queries
1. Insert code in the `Hooks.php` file - the following is inserted after the first `call` function
    ```php
    //****** inserted by Monitoring QR module ******
    public static function redcap_save_record_mon_qr($result){}
    //****** end of insert ******
    ```
    This makes the new hook `redcap_save_record_mon_qr`available to the module

1. Insert code in the `DataEntry.php` file - the following is inserted after the existing 
   `Hooks::call('redcap_save_record'...` call around line 5909
    ```php
    //****** inserted by Monitoring QR module ******
    Hooks::call('redcap_save_record_mon_qr', array($field_values_changed, PROJECT_ID, $fetched, $_GET['page'], $_GET['event_id'], $group_id, ($isSurveyPage ? $_GET['s'] : null), $response_id, $_GET['instance']));
    //****** end of insert ******
    ```
    This executes the call to the hook `redcap_save_record_mon_qr` that is handled in the module 

Disabling the module at a system level will AUTOMATICALLY do the following via the system hook
`redcap_module_system_disable`.
1. Drop the `GetMonitorQueries` stored procedure in the REDCap database automatically
1. Remove the code inserted into `Hooks.php`
1. Remove the code inserted into `DataEntry.php`

When a new version of the module becomes available, the module should be disabled and then re-enabled from the Control Center at the system level. Failure to do so may cause the module to malfunction.

#### Set up and module configuration by project ####

1. The project must use the Data Resolution Workflow option
1. The project can use the longitudinal data collection with repeating instruments and events
1. The monitoring workflow is only applicable to non-survey instruments
1. The project must include a monitor role that users performing an SDV function can use and that restricts them to 
monitoring related activities only. The project setting `monitoring-role` identifies the 'monitor role'
1. The project settings `data-entry-roles` and `data-manager-role` identify these role types. Unlike with the monitor
and data manager roles, the configuration setting allows multiple roles to be categorised as 'data entry'. The 
monitoring workflow uses these settings to determine the current user's function within the project 
i.e. whether they are monitors
1. Forms that require monitoring must have a monitoring status dropdown (aka 'monitor field') with a variable name 
ending in the suffix given in the project setting with key `monitoring-field-suffix`. The dropdown must have options 
equivalent to the following, with indexes given in project settings with the given keys
   - 'Not required' - index key `monitoring-not-required-key` indicates that monitoring is not required
   - 'Requires verification' - index key `monitoring-requires-verification-key` indicates that the verification is 
   still required
   - 'Requires verification due to data change' - index key `monitoring-requires-verification-due-to-data-change-key` 
   indicates that verification is required again as a monitored field (see below for details) has been edited since 
   verification when the monitor status was previously set to 'Verified'
   - 'Verification complete' - index key `monitoring-field-verified-key` indicates that the form is verified and 
   considered 'complete'
   - 'Verification in progress' - index key `monitoring-verification-in-progress-key` indicates that verification is 
   actively being worked on i.e. a verification query is open

    Further options can be provided but will not be used by the Monitoring QR module.
    The value in the monitor field is not directly editable and hidden from users. The value in the field is 
    automatically updated by the monitoring workflow

    An example of how to configure the monitoring status field is as follows. Create a 'Multiple Choice - Dropdown List
    (Single Answer)' question with the variable name 'form_1_monstat' where '_monstat' is the suffix set by the setting
    `monitoring-field-suffix` and the choices are as below. The Required? setting is 'No'.
 
    ```txt  
        1, Verified
        2, Requires verification
        3, Requires verification due to data change
        4, Not required
        5, Verification in progress
    ```

1. Fields requiring verification should be marked accordingly - for example, primary and secondary endpoints can be 
marked with an action tag during the design phase and therefore will be automatically marked as requiring verification.
These fields are known as 'flagged' fields. Using the project setting `monitoring-flags-regex` to create a regular
expression that will match all fields flagged for monitoring. Note: using the Embellish Fields module can help display 
these flags to the user below the question label
1. The project setting `ignore-for-monitoring-action-tag` is the action tag you wish to use to completely remove a field
from the monitoring workflow. Fields marked with this action tag cannot be monitored
1. Administrators can choose whether a field's query text can be shown inline with the question. Use the project 
settings `monitoring-role-show-inline`, `data-entry-role-show-inline` and `data-manager-role-show-inline` to display
or not based on the user's role
1. Administrators can choose what happens with monitor queries on the Resolve Issues page. Use the project setting
`resolve-issues-behaviour` to choose whether to keep the monitor queries in place and simply remove the interaction 
button, or to completely remove the row from the table. Removing the row will automatically remove all the counts 
given next to the filters as these are no longer correct as the removed rows are still counted
1. By default, monitors can raise sdv queries against any field on a form, unless the field has been marked to be 
ignored. When checked, the project setting `monitors-only-query-flagged-fields` restricts the monitor to only be able
to raise queries against flagged fields
1. To maintain the monitoring status of a form, an administrator can configure how user interaction can invalidate 
the status of previously verified forms depending on the project setting `trigger-requires-verification-for-change`. 
There are 5 options;
    - 'never' - changes to data never trigger a monitoring status update, effectively removing this feature
    - 'always' - any changes to any field on a monitored form trigger the invalidation of the monitoring status 
    regardless of the field being updated
    - 'flagged' - only fields that have an action tag matching the regular expression given in the 
    `monitoring-flags-regex` setting will trigger the invalidation
    - 'previously_queried' - only fields that have been previously queried will trigger the invalidation of the
    validation status
    - 'previously_queried_or_flagged' - if the field has been previously queried or the field is flagged, a data change
    will trigger the invalidation of the monitoring status
    
   In all cases, the trigger only fires when appropriate condition is true, and the current monitor status is 
   'Verified'. If triggered, **the monitor field status will update to 'Requires verification due to data change'**
1. By default, unless the user is a data entry user (as determined by the `data-entry-roles` setting), the data entry
   form row containing the save buttons and cancel button is hidden and not available for the user. This behaviour can
   be changed by checking the `do-not-hide-save-and-cancel-buttons-for-non-data-entry` setting so that form row is no
   longer hidden
1. By default, unless the user is a data entry user (as determined by the `data-entry-roles` setting), the data entry
   form fields are readonly for the user. This behaviour can
   be changed by checking the `do-not-make-fields-readonly` setting so that fields are no longer readonly
1. By default, the monitoring information displayed below the data entry form only includes the field name. Checking the
    `include-field-label-in-inline-form` option will include the field label in the format `field[label]` e.g. 
   dob [date of birth]
1. By default, only users with a data entry role (as determined by the `data-entry-roles` setting) can respond to 
    raised queries. Checking the `allow-data-managers-to-respond-to-queries` option will also allow data managers to 
    respond as well

#### Outline of workflow ####
 
During set up, the designers of the project can mark any fields known to require monitoring using the
built-in ActionTags mechanism. For example, a field that is used for the primary endpoint analysis may be
flagged with the ActionTag '@ENDPOINT-PRIMARY'. ActionTags are not limited by the module, and any ActionTag
can be used. However, in order for the tags to be captured by the module, they should match against the
regular expression given in the project settings with the key `monitoring-flags-regex`.

For example, using the regular expression `@ENDPOINT-[A-Z]+` will match any ActionTags prefixed with @ENDPOINT e.g.
@ENDPOINT-PRIMARY, @ENDPOINT-SAFETY. Using a regular expression gives designers maximum flexibility with how
to design their monitoring flags.

The key monitoring fields (aka 'flagged' fields) form the basis of the monitoring workflow. It is expected
that any flagged fields on a form will be in scope for monitoring. However, the module does not prevent non-flagged
fields from being monitored (depending on the project setting `monitors-only-query-flagged-fields`).

The top level workflow is as follows;

- a record is created by a site user
  - if the form requires verification (i.e. has a monitor field and flagged fields), the monitor field
    will be set to 'Requires verification' automatically when the record is created
  - if the form does not include any flagged fields, the monitor field status will be automatically set to
    'Not required'
  - the monitor query shows as NONE indicating that a monitor query has never been raised against the form

- a monitor reviews the form
  - if all flagged fields (and any other reviewed fields) are considered 'ok', the monitor field can choose to mark the
    form as verified by clicking the 'Close as verified' button. The form is considered verified and complete and
    **the monitor field status shows 'Verified'**. The monitor query shows as CLOSED.
  - the monitor can abandon any monitoring of the form by clicking the 'Close as not required button' and
    **the monitor field status shows 'Not required'**. The monitor query shows as CLOSED.
  - if any flagged fields (or other reviewed fields) need querying, the monitor documents their query
    within the monitor field data resolution workflow using the provided ui. If the `monitors-only-query-flagged-fields`
    is true, only flagged fields are available to query. If false, all fields are listed but not flagged fields are
    marked with '-- not flagged for monitoring --'. The monitor clicks the 'Raise monitor query' to raise the queries
    and **the monitor field status shows 'Verification in progress'**. The monitor query shows as OPEN.

- if verification is in process, there is an iterative process whereby
  1. date entry users respond to the raised query accordingly, using the provided ui to flag the response to each field 
     with one of the options below, and when responded to, **the monitoring status shows 'Requires verification'**. 
     The response options are; 
     - 'Value updated as per source'
     - 'Value correct as per source'
     - 'Value correct, error in source updated'
     - 'Missing data not done'
     
     For the latter two options, the user can optionally include a comment with their response. . The monitor query 
     remains OPEN.
  2. monitors review the responses and for each field being queried mark their reply as; 
     - 'accept' (default) - indicates the reply is accepted and the query on the field is complete
     - 'reraise' - indicates they are not satisfied with the response and that the query is being sent back. The monitor
     can optionally update the query text. If the text is not updated, it is sent back unchanged. . The monitor query 
     remains as OPEN.
     
     Once all fields have been handled, if there are any fields to be 'reraised' the monitor clicks the 'Send back for
     further attention' button and monitor field status remains as 'Verification in progress'. If the monitor opts to
     send back queries without setting at least one field as 'reraise', the system alerts the monitor that they can only
     send back queries when they have marked fields as 'reraised'.
     
  3. alternatively, the monitor can simply mark as queries as complete by clicking the 'Close as verified' button
     thereby setting **the monitor field status to 'Verified'**, or abandon the query by clicking the 'Close as not
     required' button and thereby setting **the monitor field status to 'Not required'**. . The monitor query shows as 
     CLOSED.

- at any time, the monitor can click the 'Show history' button to view the previous states of the querying

- if the form has been previously marked as 'Verified' by a monitor and a data entry user edits a form by amending a 
  field, **the monitor field status may update to 'Requires verification due to data change'** depending on the project
  setting `trigger-requires-verification-for-change`

- a log of monitoring query status can be accessed via the link 'Monitoring QR'. The log gives details of the current
  status of any forms and their fields that are available for monitoring. The page has the following features;
  - options for filtering the log based on many parameters, such as record, current query status, date of edit, 
    responses, query text content and so on
  - depending on the current query status, the table provides different information. For CLOSED queries or forms that
    have never received a monitor query (i.e. those with query status of NONE), only details identifying the form and 
    the monitor and query status are shown, whereas for OPEN queries details of each field are given. **NOTE: by default,
    newly created forms where a monitor query have never been raised are not shown**. To make these available, check the
    option 'always include items without a timestamp'.
  - use the eye icon to navigate to the relevant form. To return to the log use the browser's 'go back' button
  - the monitoring log can be downloaded as a csv using the available buttons;
    - 'Export current page' - will export the log entries as visible on the screen for the current page
    - 'Export all pages' - will export all log entries as determined by the filters
    - 'Export everything ignoring filters' - exports all logs ignoring the filters entirely

#### Automation Testing

The module includes comprehensive **Cypress automated** tests using the **Cucumber/Gherkin framework**. To set up Cypress, refer to the following repository:  
https://github.com/vanderbilt-redcap/redcap_cypress

We use a custom Docker instance, **CCTC_REDCap_Docker**, instead of `redcap_docker`. This instance mirrors our Live environment by using the same versions of **MariaDB** and **PHP**.

All automated test scripts are located in the `automated_tests` directory. These scripts can also be used to manually test the external module. The directory contains:
- Custom step definitions created by our team
- Fixture files
- User Requirement Specification (URS) documents
- Feature test scripts