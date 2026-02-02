Feature: E.128.500 - The system shall support the ability to hide Monitoring QR external module from non-admins in the list of enabled modules on each project.

  As a REDCap end user
  I want to see that Monitoring QR is functioning as expected

Scenario: E.128.500 - Hide this module from non-admins in the list of enabled modules on each project
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    When I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I should NOT see "Monitoring QR - v1.0.0"
    When I click on the button labeled "Enable a module"
    And I wait for 2 seconds
    Then I should see "Available Modules"
    And I click on the button labeled "Enable" in the row labeled "Monitoring QR"
    And I wait for 1 second
    And I click on the button labeled "Enable"
    Then I should see "Monitoring QR - v1.0.0"
    
    When I click on the button labeled "Configure"
    And I check the checkbox labeled "Hide this module from non-admins in the list of enabled modules on each project"
    And I click on the button labeled "Save"
    Then I should see "Monitoring QR - v1.0.0"
  
    When I create a new project named "E.128.500" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "fixtures/cdisc_files/Project_redcap_val_nodata.xml", and clicking the "Create Project" button
    
    # Enable external module
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled "Enable" in the row labeled "Monitoring QR - v1.0.0"
    Then I should see "Monitoring QR - v1.0.0"

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
    And I click on the link labeled "E.128.500"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should NOT see "Monitoring QR - v1.0.0"
    And I logout

    # Disable 'Hide this module from non-admins in the list of enabled modules on each project'
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "Monitoring QR - v1.0.0"
    When I click on the button labeled "Configure"
    And I uncheck the checkbox labeled "Hide this module from non-admins in the list of enabled modules on each project"
    And I click on the button labeled "Save"
    Then I should see "Monitoring QR - v1.0.0"
    And I logout

    Given I login to REDCap with the user "Test_User1"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.128.500"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should see "Monitoring QR - v1.0.0"
    And I logout

    # Enable from project - 'Hide this module from non-admins in the list of enabled modules on each project'
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.128.500"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    Then I should see "Monitoring QR - v1.0.0"
    When I click on the button labeled "Configure"
    And I check the checkbox labeled "Hide this module from non-admins in the list of enabled modules on this project"
    And I enter "_monstat" into the input field labeled "Provide the suffix used to identify the monitoring field on a form"
    And I enter "@ENDPOINT-\w+" into the textarea field labeled "Provide the regex used to identify fields that should be monitored"
    And I select "Monitor" on the dropdown field labeled "What role do monitors use?"
    And I select "DataEntry" on the dropdown field labeled "1. What roles do data entry users use?"
    And I select "DataManager" on the dropdown field labeled "What role do data managers use?"
    Then I enter "4" into the input field labeled "Id of monitoring status field meaning 'Not required'"
    And I enter "2" into the input field labeled "Id of monitoring status field meaning 'Requires verification'"
    And I enter "3" into the input field labeled "Id of monitoring status field meaning 'Requires verification due to data change'"
    And I enter "1" into the input field labeled "Id of monitoring status field meaning 'Verification complete'"
    And I enter "5" into the input field labeled "Id of monitoring status field meaning 'Verification in progress'"
    And I select "Always whenever any field is updated" on the dropdown field labeled "A form's monitoring status is automatically set to 'Requires verification due to data change'"
    And I scroll to the field labeled "When the user visits the Resolve Issues page, handle monitor status fields by"
    And I select "Hiding the button to interact with the query but leave the row in place" on the dropdown field labeled "When the user visits the Resolve Issues page, handle monitor status fields by"
    And I click on the button labeled "Save"
    Then I should see "Monitoring QR - v1.0.0"
    And I logout

    Given I login to REDCap with the user "Test_User1"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.128.500"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should NOT see "Monitoring QR - v1.0.0"
    And I logout

    # Disable external module in Control Center
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    And I click on the button labeled "Disable"
    Then I should see "Disable module?"
    When I click on the button labeled "Disable module"
    Then I should NOT see "Monitoring QR - v1.0.0"
    And I logout

    # Verify no exceptions are thrown in the system
    Given I open Email
    Then I should NOT see an email with subject "REDCap External Module Hook Exception - monitoring_qr"