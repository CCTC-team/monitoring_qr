Feature: E.128.3700 - The system shall show filter values and monitoring query text as plain text in the Monitoring QR external module.

  As a REDCap end user
  I want values containing quotes or HTML to be displayed exactly as they were entered
  So that the content of a value cannot change how the Monitoring QR page behaves

  Scenario: Enable external Module from Control Center
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    # EMAIL ADDRESS SET FOR REDCAP ADMIN - without it, emails are not send out from system
    When I click on the link labeled "General Configuration"
    Then I should see "General Configuration"
    When I enter "redcap@test.instance" into the input field labeled "Email Address of REDCap Administrator"
    And I click on the button labeled "Save Changes"
    Then I should see "Your system configuration values have now been changed"

    Given I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I click on the button labeled "Disable"
    Then I should see "Disable module?"
    When I click on the button labeled "Disable module"
    Then I should NOT see "Monitoring QR - v1.2.0"
    When I click on the button labeled "Enable a module"
    And I wait for 2 seconds
    Then I should see "Available Modules"
    And I click on the button labeled "Enable" in the row labeled "Monitoring QR"
    And I wait for 1 second
    And I click on the button labeled "Enable"
    Then I should see "Monitoring QR - v1.2.0"
 
  Scenario: Enable external module in project
    Given I create a new project named "E.128.3700" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "fixtures/cdisc_files/ProjectTypes/NonLongitudinal_NonRepeatingInstruments_noDAGs.xml", and clicking the "Create Project" button
    And I click on the second link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should NOT see "Monitoring QR - v1.2.0"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled "Enable" in the row labeled "Monitoring QR - v1.2.0"
    Then I should see "Monitoring QR - v1.2.0"

    #ACTION: Enable the Data Resolution Workflow
    Given I click on the link labeled "Setup"
    And I click on the button labeled "Additional customizations"
    And I select "Data Resolution Workflow" on the dropdown field labeled "Enable:"
    Then I click on the button labeled "Save"
    Then I should see "The Data Resolution Workflow has now been enabled!"
    And I click on the button labeled "Close"

    # Adding Test_User2 to DataEntryPI role
    Given I click on the link labeled "User Rights"
    When I enter "Test_User2" into the field with the placeholder text of "Assign new user to role"
    And I click on the button labeled "Assign to role"
    And I select "DataEntryPI" on the dropdown field labeled "Select Role" on the role selector dropdown
    When I click on the button labeled "Assign"
    Then I should see "Test User2" within the "DataEntryPI" row of the column labeled "Username" of the User Rights table

    # Adding Test_User4 to Monitor role
    When I enter "Test_User4" into the field with the placeholder text of "Assign new user to role"
    And I click on the button labeled "Assign to role"
    And I select "Monitor" on the dropdown field labeled "Select Role" on the role selector dropdown
    When I click on the button labeled "Assign"
    Then I should see "Test User4" within the "Monitor" row of the column labeled "Username" of the User Rights table

    # ACTION: Import data
    Given I click on the link labeled "Data Import Tool"
    And I upload a "csv" format file located at "fixtures/import_files/NonLongitudinal_NonRepeatingInstruments_noDAGs.csv", by clicking the button near "Select your CSV data file" to browse for the file, and clicking the button labeled "Upload File" to upload the file
    And I should see "Your document was uploaded successfully and is ready for review."
    And I click on the button labeled "Import Data"
    Then I should see "Import Successful!"

    # Configure external module in project
    And I click on the second link labeled "Manage"
    And I click on the button labeled "Configure"
    Then I should see "Configure Module"
    And I enter "_monstat" into the input field labeled "Provide the suffix used to identify the monitoring field on a form"
    And I enter "@ENDPOINT-\w+" into the textarea field labeled "Provide the regex used to identify fields that should be monitored"
    And I select "Monitor" on the dropdown field labeled "What role do monitors use?"
    And I select "DataEntryPI" on the dropdown field labeled "1. What roles do data entry users use?"
    And I select "DataManager" on the dropdown field labeled "What role do data managers use?"
    Then I enter "4" into the input field labeled "Id of monitoring status field meaning 'Not required'"
    And I enter "2" into the input field labeled "Id of monitoring status field meaning 'Requires verification'"
    And I enter "3" into the input field labeled "Id of monitoring status field meaning 'Requires verification due to data change'"
    And I enter "1" into the input field labeled "Id of monitoring status field meaning 'Verification complete'"
    And I enter "5" into the input field labeled "Id of monitoring status field meaning 'Verification in progress'"
    And I select "Always whenever any field is updated" on the dropdown field labeled "A form's monitoring status is automatically set to 'Requires verification due to data change'"
    And I scroll to the field labeled "When the user visits the Resolve Issues page, handle monitor status fields by"
    And I select "Hiding the button to interact with the query but leave the row in place" on the dropdown field labeled "When the user visits the Resolve Issues page, handle monitor status fields by"
    Then I click on the button labeled "Save"
    And I should see "Monitoring QR - v1.2.0"
    And I logout

    Given I login to REDCap with the user "Test_User4"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.128.3700"
    And I click on the link labeled "Record Status Dashboard"
    Then I should see "Record Status Dashboard (all records)"
    And I click on the icon in the column labeled "Data Types" and the row labeled "2"
    Then I should see "Monitor query status: NONE"
    When I enter "O'Brien <b>bold</b> query" in the column "Query to raise" for the field "ptname"
    And I enter "=1+1 query" in the column "Query to raise" for the field "notesbox"
    And I click on the button labeled "Raise monitor query"
    Then I should see "Monitor query status: OPEN"

  Scenario: Query text containing quotes and HTML is shown as entered
    Given I click on the link labeled "Monitoring QR"
    Then I should see "O'Brien <b>bold</b> query"

  Scenario: Filtering on a value containing an apostrophe finds the query and keeps the value
    Given I click on the link labeled "Monitoring QR"
    When I enter "O'Brien" into the Monitoring QR filter "dataquerytext"
    Then the Monitoring QR filter "dataquerytext" should have the value "O'Brien"
    And I should see "O'Brien <b>bold</b> query"

  Scenario: A filter value containing a script stays inside its input
    Given I click on the link labeled "Monitoring QR"
    When I enter "'><script>window.mqInjected=1</script>" into the Monitoring QR filter "dataquerytext"
    Then the Monitoring QR filter "dataquerytext" should have the value "'><script>window.mqInjected=1</script>"
    And I should see "No records matching your query were found."
    And the script from the Monitoring QR filter should not have run

  Scenario: The negated query status filters exclude the matching status
    Given I click on the link labeled "Monitoring QR"
    When I enter "NOT-OPEN" into the Monitoring QR filter "currentstatus"
    Then the Monitoring QR filter "currentstatus" should have the value "NOT-OPEN"
    And I should NOT see "O'Brien <b>bold</b> query"
    When I enter "OPEN" into the Monitoring QR filter "currentstatus"
    Then I should see "O'Brien <b>bold</b> query"

  Scenario: The export protects spreadsheet users from formula values
    Given I click on the link labeled "Monitoring QR"
    Then the Monitoring QR export requested directly should contain ' =1+1 query'
    And the Monitoring QR export requested directly should NOT contain '"=1+1 query'

  Scenario: A date filter containing a script stays inside its input
    Given I click on the link labeled "Monitoring QR"
    When I enter "'><script>window.mqInjected=1" into the Monitoring QR filter "enddt"
    Then the Monitoring QR filter "enddt" should have the value "'><script>window.mqI"
    And the script from the Monitoring QR filter should not have run
    And I logout

  Scenario: Disable external module
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I click on the button labeled "Disable"
    Then I should see "Disable module?"
    When I click on the button labeled "Disable module"
    Then I should NOT see "Monitoring QR - v1.2.0"
    And I logout
