Feature: E.128.100 - The system shall support the ability to enable/disable Monitoring QR external module.

  As a REDCap end user
  I want to see that Monitoring QR is functioning as expected

  Scenario: E.128.100 - Enable external module - Default settings
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    # EMAIL ADDRESS SET FOR REDCAP ADMIN - without it, emails are not send out from system
    When I click on the link labeled "General Configuration"
    Then I should see "General Configuration"
    When I enter "redcap@test.instance" into the input field labeled "Email Address of REDCap Administrator"
    And I click on the button labeled "Save Changes"
    Then I should see "Your system configuration values have now been changed"

    Given I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Module Manager"
    And I should NOT see "Monitoring QR - v0.0.0"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled Enable for the external module named "Monitoring QR"
    And I click on the button labeled "Enable" in the dialog box
    Then I should see "Monitoring QR - v0.0.0"
    And I logout
    
    Given I login to REDCap with the user "Test_User1"
    When I create a new project named "E.128.100.100" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "redcap_val/Project_redcap_val_nodata.xml", and clicking the "Create Project" button
    And I should NOT see a link labeled exactly "Manage"
    And I logout

  Scenario: E.128.200 - Enable module on all projects by default
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Module Manager"
    And I should see "Monitoring QR - v0.0.0"
    And I should NOT see "Enabled for All Projects"
    When I click on the button labeled exactly "Configure"
    And I check the checkbox labeled "Enable module on all projects by default"
    And I click on the button labeled "Save"
    Then I should see "Monitoring QR - v0.0.0"
    And I should see "Enabled for All Projects"
    And I logout
    
    Given I login to REDCap with the user "Test_User1"
    When I create a new project named "E.128.100.200" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "redcap_val/Project_redcap_val_nodata.xml", and clicking the "Create Project" button
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should see "Monitoring QR - v0.0.0"
    And I should see "Enabled for All Projects"
    And I logout

  Scenario: E.128.300 - Make module discoverable by users
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Module Manager"
    And I should see "Monitoring QR - v0.0.0"
    And I should see "Enabled for All Projects"
    And I should NOT see "Discoverable"
    When I click on the button labeled exactly "Configure"
    And I uncheck the checkbox labeled "Enable module on all projects by default"
    And I check the checkbox labeled "Make module discoverable by users"
    And I click on the button labeled "Save"
    Then I should see "Monitoring QR - v0.0.0"
    And I should NOT see "Enabled for All Projects"
    And I should see "Discoverable"
    And I logout
    
    Given I login to REDCap with the user "Test_User1"
    When I create a new project named "E.128.100.300" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "redcap_val/Project_redcap_val_nodata.xml", and clicking the "Create Project" button
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should NOT see "Monitoring QR - v0.0.0"

    When I click on the button labeled "View available modules"
    Then I should see "Monitoring QR - v0.0.0"
    And I should see "Discoverable"
    And I should NOT see "Activation request is pending"
    When I click on the button labeled Request Activation for the external module named "Monitoring QR"
    Then I should see "SUCCESS" in the dialog box
    And I click on the button labeled "Close" in the dialog box
    And I should NOT see "Monitoring QR - v0.0.0"

    When I click on the button labeled "View available modules"
    Then I should see "Monitoring QR - v0.0.0"
    And I should see "Discoverable"
    And I should see "Activation request is pending"
    And I logout

    ## Commenting this as HTML is not loading into iframe
    ## Hence this is done manually
    # Given I login to REDCap with the user "Test_Admin"
    # When I click on the link labeled "Control Center"
    # Then I should see a link labeled "To-Do List"

    # Given I click on the link labeled "To-Do List"
    # Then I should see "Pending Requests"
    # And I should see the "Activate external module" request created for the project named "E.128.100.300" within the Pending Requests table
    # When I click on the process request icon for the "Activate external module" request created for the project named "E.128.100.300" within the Pending Requests table
    # # HTML is not loading into iFrame
    # Then I should see "Enable module 'Monitoring QR - '?" in the dialog box in the iframe
    # And I click on the button labeled "Enable" in the dialog box in the iframe
    # And I close the iframe window
    # Then I should see the "Activate external module" request created for the project named "E.128.100.300" within the Completed & Archived Requests table
    # And I logout

    # Given I login to REDCap with the user "Test_User1"
    # When I click on the link labeled "My Projects"
    # And I click on the link labeled "E.128.100.300"
    # And I click on the link labeled exactly "Manage"
    # Then I should see "External Modules - Project Module Manager"
    # And I should see "Monitoring QR - v0.0.0"
    # And I should see "Discoverable"
    # And I logout

  Scenario: E.128.400 - Allow non-admins to enable this module on projects
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"   
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Module Manager"
    And I should see "Monitoring QR - v0.0.0"
    And I should see "Discoverable"
    When I click on the button labeled exactly "Configure"
    And I check the checkbox labeled "Allow non-admins to enable this module on projects"
    And I click on the button labeled "Save"
    Then I should see "Monitoring QR - v0.0.0"
    And I should see "Discoverable"
    And I logout
    
    Given I login to REDCap with the user "Test_User1"
    When I create a new project named "E.128.100.400" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "redcap_val/Project_redcap_val_nodata.xml", and clicking the "Create Project" button
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should NOT see "Monitoring QR - v0.0.0"

    When I click on the button labeled "View available modules"
    Then I should see "Monitoring QR - v0.0.0"
    And I should see "Discoverable"
    And I click on the button labeled Enable for the external module named "Monitoring QR - v0.0.0"
    Then I should see "Monitoring QR - v0.0.0"
    And I logout

  Scenario: E.128.500 - Hide this module from non-admins in the list of enabled modules on each project
    #  Enable 'Hide this module from non-admins in the list of enabled modules on each project'
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled exactly "Manage"
    Then I should see "Monitoring QR - v0.0.0"
    And I should see "Discoverable"
    When I click on the button labeled exactly "Configure"
    And I uncheck the checkbox labeled "Make module discoverable by users"
    And I uncheck the checkbox labeled "Allow non-admins to enable this module on projects"
    And I check the checkbox labeled "Hide this module from non-admins in the list of enabled modules on each project"
    And I click on the button labeled "Save"
    Then I should see "Monitoring QR - v0.0.0"
    And I should NOT see "Discoverable"
  
    When I create a new project named "E.128.100.500" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "redcap_val/Project_redcap_val_nodata.xml", and clicking the "Create Project" button
    
    # Enable external module
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled Enable for the external module named "Monitoring QR - v0.0.0"
    Then I should see "Monitoring QR - v0.0.0"

    # Add User Test_User1 with Project Setup & Design User Rights
    When I click on the link labeled "User Rights"
    And I enter "Test_User1" into the input field labeled "Add with custom rights"
    And I click on the button labeled "Add with custom rights"
    Then I check the User Right named "Project Setup & Design"
    And I click on the button labeled "Add user"
    Then I should see "successfully added"
    And I logout

    Given I login to REDCap with the user "Test_User1"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.128.100.500"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should NOT see "Monitoring QR - v0.0.0"
    And I logout

    # Disable 'Hide this module from non-admins in the list of enabled modules on each project'
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled exactly "Manage"
    Then I should see "Monitoring QR - v0.0.0"
    When I click on the button labeled exactly "Configure"
    And I uncheck the checkbox labeled "Hide this module from non-admins in the list of enabled modules on each project"
    And I click on the button labeled "Save"
    Then I should see "Monitoring QR - v0.0.0"
    And I logout

    Given I login to REDCap with the user "Test_User1"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.128.100.500"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should see "Monitoring QR - v0.0.0"
    And I logout

    # Enable from project - 'Hide this module from non-admins in the list of enabled modules on each project'
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.128.100.500"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    Then I should see "Monitoring QR - v0.0.0"
    When I click on the button labeled exactly "Configure"
    And I check the checkbox labeled "Hide this module from non-admins in the list of enabled modules on this project"
    And I enter "_monstat" into the input field labeled "Provide the suffix used to identify the monitoring field on a form" in the dialog box
    And I enter "@ENDPOINT-\w+" into the textarea field labeled "Provide the regex used to identify fields that should be monitored" in the dialog box
    And I select "Monitor" on the dropdown field labeled "What role do monitors use?" in the dialog box
    And I select "DataEntry" on the dropdown field labeled "1. What roles do data entry users use?" in the dialog box
    And I select "DataManager" on the dropdown field labeled "What role do data managers use?" in the dialog box
    Then I enter "4" into the input field labeled "Id of monitoring status field meaning 'Not required'" in the dialog box
    And I enter "2" into the input field labeled "Id of monitoring status field meaning 'Requires verification'" in the dialog box
    And I enter "3" into the input field labeled "Id of monitoring status field meaning 'Requires verification due to data change'" in the dialog box
    And I enter "1" into the input field labeled "Id of monitoring status field meaning 'Verification complete'" in the dialog box
    And I enter "5" into the input field labeled "Id of monitoring status field meaning 'Verification in progress'" in the dialog box
    And I select "Always whenever any field is updated" on the dropdown field labeled "A form's monitoring status is automatically set to 'Requires verification due to data change'" in the dialog box
    And I scroll to the field labeled "When the user visits the Resolve Issues page, handle monitor status fields by"
    And I select "Hiding the button to interact with the query but leave the row in place" on the dropdown field labeled "When the user visits the Resolve Issues page, handle monitor status fields by" in the dialog box
    And I click on the button labeled "Save"
    Then I should see "Monitoring QR - v0.0.0"
    And I logout

    Given I login to REDCap with the user "Test_User1"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.128.100.500"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should NOT see "Monitoring QR - v0.0.0"
    And I logout

  Scenario: Module configuration permissions in projects
    # Enable - Require Project Setup/Design privilege
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Module Manager"
    And I should see "Monitoring QR - v0.0.0"
    When I click on the button labeled exactly "Configure"
    Then I should see the dropdown field labeled "Module configuration permissions in projects" with the option "Require Project Setup/Design privilege" selected
    And I click on the button labeled "Save"
    Then I should see "Monitoring QR - v0.0.0"

    When I create a new project named "E.128.100.600" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "redcap_val/Project_redcap_val_nodata.xml", and clicking the "Create Project" button

    # Enable external module
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled Enable for the external module named "Monitoring QR - v0.0.0"
    Then I should see "Monitoring QR - v0.0.0"

    #VERIFY
    When I click on the link labeled "User Rights"
    And I enter "Test_User1" into the input field labeled "Add with custom rights"
    And I click on the button labeled "Add with custom rights"
    When I check the User Right named "Project Setup & Design"
    Then I should see a checkbox labeled "Monitoring QR" that is checked
    And I click on the button labeled "Add user"
    Then I should see "successfully added"

    # Enable - Require module-specific user privilege
    When I click on the link labeled "Control Center"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Module Manager"
    And I should see "Monitoring QR - v0.0.0"
    When I click on the button labeled exactly "Configure"
    And I select "Require module-specific user privilege" on the dropdown field labeled "Module configuration permissions in projects"
    And I click on the button labeled "Save"
    Then I should see "Monitoring QR - v0.0.0"

    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.128.100.600"

    #VERIFY
    When I click on the link labeled "User Rights"
    And I enter "Test_User2" into the input field labeled "Add with custom rights"
    And I click on the button labeled "Add with custom rights"
    When I check the User Right named "Project Setup & Design"
    Then I should see a checkbox labeled "Monitoring QR" that is unchecked
    And I check the checkbox labeled "Monitoring QR"
    And I click on the button labeled "Add user"
    Then I should see "successfully added"

  Scenario: E.128.600 - View Usage of the external module
    When I click on the link labeled "Control Center"
    And I click on the link labeled exactly "Manage"
    Then I should see "Monitoring QR - v0.0.0"
    When I click on the button labeled "View Usage"
    Then I should see a link labeled "E.128.100.400" in the dialog box
    And I should see a link labeled "E.128.100.500" in the dialog box
    And I should see a link labeled "E.128.100.600" in the dialog box
    When I click on the link labeled "E.128.100.400" in the dialog box
    # # For Request Activation of project E.128.100.300 which doesn't work in ATS.
    # # Can be checked manually
    # And I should see a link labeled "E.128.100.300" in the dialog box
    Then I should see "Project Home"
    And I should see "E.128.100.400"
  
  Scenario: E.128.100 - Disable external module
    # Disable external module in project E.128.100.400
    Given I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should see "Monitoring QR - v0.0.0"
    When I click on the button labeled exactly "Disable"
    Then I should see "Disable module?" in the dialog box
    When I click on the button labeled "Disable module" in the dialog box
    Then I should NOT see "Monitoring QR - v0.0.0"

    # Disable external module in project E.128.100.500
    Given I click on the link labeled "My Projects"
    When I click on the link labeled "E.128.100.500"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should see "Monitoring QR - v0.0.0"
    When I click on the button labeled exactly "Disable"
    Then I should see "Disable module?" in the dialog box
    When I click on the button labeled "Disable module" in the dialog box
    Then I should NOT see "Monitoring QR - v0.0.0"

    # Disable external module in project E.128.100.600
    Given I click on the link labeled "My Projects"
    When I click on the link labeled "E.128.100.600"
    And I click on the link labeled exactly "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should see "Monitoring QR - v0.0.0"
    When I click on the button labeled exactly "Disable"
    Then I should see "Disable module?" in the dialog box
    When I click on the button labeled "Disable module" in the dialog box
    Then I should NOT see "Monitoring QR - v0.0.0"

    # Disable external module in Control Center
    Given I click on the link labeled "Control Center"
    When I click on the link labeled exactly "Manage"
    Then I should see "Monitoring QR - v0.0.0"
    When I click on the button labeled "View Usage"
    Then I should see "None" in the dialog box
    And I should NOT see "E.128.100.400" in the dialog box
    And I should NOT see "E.128.100.500" in the dialog box
    And I should NOT see "E.128.100.600" in the dialog box
    And I close the dialog box for the external module "Monitoring QR"
    And I click on the button labeled exactly "Disable"
    Then I should see "Disable module?" in the dialog box
    When I click on the button labeled "Disable module" in the dialog box
    Then I should NOT see "Monitoring QR - v0.0.0"
    And I logout

    # Verify no exceptions are thrown in the system
    Given I open Email
    Then I should NOT see an email with subject "REDCap External Module Hook Exception - monitoring_qr"