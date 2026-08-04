Feature: E.128.3600 - The system shall record configuration changes for the Monitoring QR external module (who, when, old->new) to the module's View Logs page.

  As a REDCap administrator
  I want every configuration change to be written to the module's External Module Logs
  So that there is an audit trail of who changed which setting, when, and from what value to what.

  Scenario: Enable external module from Control Center
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I should NOT see "Monitoring QR - v1.1.0"
    When I click on the button labeled "Enable a module"
    And I wait for 2 seconds
    Then I should see "Available Modules"
    And I click on the button labeled "Enable" in the row labeled "Monitoring QR"
    And I wait for 1 second
    And I click on the button labeled "Enable"
    Then I should see "Monitoring QR - v1.1.0"

  Scenario: First configuration save logs the initial values
    # This module has 12 REQUIRED settings, so the Configure form will not save
    # until all of them are filled -- the first save therefore logs 14 entries.
    # The assertions below deliberately target the LAST two keys in config.json
    # order (include-field-label-in-inline-form, then
    # allow-data-managers-to-respond-to-queries), because View Logs shows newest
    # first: those two stay the first and second entries no matter how many
    # required fields precede them. The project fixture supplies the Monitor /
    # DataEntryPI / DataManager roles the role-list settings need.
    Given I create a new project named "E.128.3600.100" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "fixtures/cdisc_files/E1281500.xml", and clicking the "Create Project" button
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    When I click on the button labeled "Enable a module"
    And I click on the button labeled "Enable" in the row labeled "Monitoring QR - v1.1.0"
    Then I should see "Monitoring QR - v1.1.0"

    Given I click on the button labeled "Configure"
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
    And I select "When the updated field is flagged" on the dropdown field labeled "A form's monitoring status is automatically set to 'Requires verification due to data change'"
    And I scroll to the field labeled "When the user visits the Resolve Issues page, handle monitor status fields by"
    And I select "Hiding the button to interact with the query but leave the row in place" on the dropdown field labeled "When the user visits the Resolve Issues page, handle monitor status fields by"
    And I check the checkbox labeled "When checked, the first column in the inline table below the data entry"
    And I check the checkbox labeled "When checked, users with the data manager role can also respond to mon"
    Then I click on the button labeled "Save"
    And I should see "Monitoring QR - v1.1.0"

    #VERIFY - the audit trail on the module's own View Logs page
    When I click on the link labeled "View Logs"
    Then I should see "External Module Logs"
    And I should see a table header and row containing the following values in a table:
      | Module        | Message                         | UserName   |
      | monitoring_qr | Configuration changed (project) | Test_Admin |

    When I click on the first button labeled "Show Parameters"
    Then I should see "Log Entry Parameters"
    And I should see a table header and row containing the following values in a table:
      | Name      | Value                                     |
      | setting   | allow-data-managers-to-respond-to-queries |
      | old_value | (empty)                                   |
      | new_value | 1                                         |
    And I click on the button labeled "Close"
    Then I should see "External Module Logs"

    When I click on the second button labeled "Show Parameters"
    Then I should see "Log Entry Parameters"
    And I should see a table header and row containing the following values in a table:
      | Name      | Value                              |
      | setting   | include-field-label-in-inline-form |
      | old_value | (empty)                            |
      | new_value | 1                                  |

  Scenario: Changing a setting logs an old->new audit entry
    # rctf starts each scenario from a clean browser page, so re-navigate to the
    # project fresh (same pattern as the other continuation scenarios).
    Given I login to REDCap with the user "Test_Admin"
    When I click on the link labeled "My Projects"
    And I click on the link labeled "E.128.3600.100"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Project Module Manager"
    And I should see "Monitoring QR - v1.1.0"

    # Change ONE setting, so it is the only entry this save produces.
    When I click on the button labeled "Configure"
    Then I should see "Configure Module"
    And I clear field and enter "_monstatus" into the input field labeled "Provide the suffix used to identify the monitoring field on a form"
    Then I click on the button labeled "Save"
    And I should see "Monitoring QR - v1.1.0"

    #VERIFY - the audit trail on the module's own View Logs page
    When I click on the link labeled "View Logs"
    Then I should see "External Module Logs"
    And I should see a table header and row containing the following values in a table:
      | Module        | Message                         | UserName   |
      | monitoring_qr | Configuration changed (project) | Test_Admin |

    When I click on the first button labeled "Show Parameters"
    Then I should see "Log Entry Parameters"
    And I should see a table header and row containing the following values in a table:
      | Name      | Value                   |
      | setting   | monitoring-field-suffix |
      | old_value | _monstat                |
      | new_value | _monstatus              |
    And I click on the button labeled "Close"
    Then I should see "External Module Logs"

    # Disable the external module from the Control Center
    When I click on the link labeled "Control Center"
    And I click on the link labeled "Manage"
    Then I should see "External Modules - Module Manager"
    And I click on the button labeled "Disable"
    Then I should see "Disable module?"
    When I click on the button labeled "Disable module"
    Then I should NOT see "Monitoring QR - v1.1.0"

  # Kept as its own scenario: "I open Email" leaves the browser on MailHog, a
  # different origin to REDCap, and appending it to the end of the scenario above
  # makes that scenario fail at its FIRST step against a blank page.
  Scenario: No hook exceptions are raised
    Given I open Email
    Then I should NOT see an email with subject "REDCap External Module Hook Exception - monitoring_qr"
