//Add any of your own step definitions here
const { Given, defineParameterType } = require('@badeball/cypress-cucumber-preprocessor')

defineParameterType({
    name: 'emTableName',
    regexp: /monitoring logging/
})

const emTableName = {
    'monitoring logging' : '#monitor-query-data-log-table'
}

// Requests the export page directly rather than through its buttons.
function requestExport() {
    return cy.url().then((url) => {
        const pid = new URL(url).searchParams.get('pid')
        return cy.window().then((win) => cy.request({
            url: `${win.app_path_webroot}ExternalModules/?prefix=monitoring_qr&page=csv_export&pid=${pid}&export_type=everything`,
            failOnStatusCode: false
        }))
    })
}


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I should NOT see {string} within the data entry field labeled {string}
 * @param {string} fieldOptions - field options visible
 * @param {string} label - Field Label
 * @description verifies data entry field does not contain text
 */
Given("I should NOT see {string} within the data entry field labeled {string}", (fieldOptions, label) => {
    cy.get('#questiontable').find('td').contains(label).parents('tr').should('not.contain', fieldOptions)
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I enter {string} in the column {string} for the field {string}
 * @param {string} text - text to enter
 * @param {string} col - column in monitoring table
 * @param {string} fieldLabel - field label
 * @description enters text in the input field for the specified row and column in the monitoring table
 */
Given('I enter {string} in the column {string} for the field {string}', (text, col, fieldLabel) => {
    cy.table_cell_by_column_and_row_label(col, fieldLabel, '#mon-q-fields-table').find('textarea').clear().type(text)
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I should NOT see {string} in the monitoring table
 * @param {string} text - text that should not be seen in the monitoring table
 * @description verifies text is not visible in the monitoring table
 */
Given('I should NOT see {string} in the monitoring table', (text) => {
    cy.get('#mon-q-fields-table').should('not.contain', text)
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I select {string} in the dropdown field in column {string} for the field {string}
 * @param {string} option - option to select
 * @param {string} col - column in monitoring table
 * @param {string} fieldLabel - field label
 * @description selects the dropdown option for the specified row and column in the monitoring table
 */
Given('I select {string} in the dropdown field in column {string} for the field {string}', (option, col, fieldLabel) => {
    cy.table_cell_by_column_and_row_label(col, fieldLabel, '#mon-q-fields-table').find('select').select(option)  
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I should NOT see the {monTable} table
 * @param {string} monTable - available options: 'monitoring', 'monitoring history', 'monitoring logging'
 * @description verifies monitoring table does not exists
 */
Given('I should NOT see the monitoring table', () => {
    cy.get('#mon-q-fields-table').should('not.exist')
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I click on the {string} view icon
 * @param {string} ordinal - available options: 'first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth', 'ninth', 'tenth', 'eleventh', 'twelfth', 'thirteenth', 'fourteenth', 'fifteenth', 'sixteenth', 'seventeenth', 'eighteenth', 'nineteenth', 'twentieth', 'last'
 * @description clicks on the view icon in the monitoring table
 */
Given('I click on the {string} view icon', (ordinal) => {
    index = window.ordinalChoices[ordinal]
    cy.get('.fa-eye').eq(index).click()
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I should see a button labeled {string} disabled
 * @param {string} label - label on button
 * @description verifies the button is disabled
 */
Given('I should see a button labeled {string} disabled', (label) => {
    cy.get('input[type=button][value*="' + label +'"]').should('be.disabled')
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I should see the monitoring status {string}
 * @param {string} label - monitoring status
 * @description verifies the monitoring status
 */
Given('I should see the monitoring status {string}', (label) => {
    cy.get('tr[class=labelrc]').contains(label)
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I enter {string} into the Monitoring QR filter {string}
 * @param {string} text - text to filter on
 * @param {string} id - id of the filter input
 * @description enters text in a filter input and submits the filter form
 */
Given('I enter {string} into the Monitoring QR filter {string}', (text, id) => {
    cy.get(`#${id}`).invoke('val', text)
    cy.get('#filterForm').then(($form) => $form[0].submit())
    cy.get(`#${id}`).should('exist')
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the Monitoring QR filter {string} should have the value {string}
 * @param {string} id - id of the filter input
 * @param {string} value - expected value
 * @description verifies the value shown by a filter input
 */
Given('the Monitoring QR filter {string} should have the value {string}', (id, value) => {
    cy.get(`#${id}`).should('have.value', value)
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the Monitoring QR export requested directly should contain {string}
 * @param {string} text - expected text
 * @description verifies the export contains the text when the export page is requested directly
 */
Given('the Monitoring QR export requested directly should contain {string}', (text) => {
    requestExport().its('body').should('include', text)
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the Monitoring QR export requested directly should NOT contain {string}
 * @param {string} text - text that should be absent
 * @description verifies the export does not contain the text when the export page is requested directly
 */
Given('the Monitoring QR export requested directly should NOT contain {string}', (text) => {
    requestExport().its('body').should('not.include', text)
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example the script from the Monitoring QR filter should not have run
 * @description verifies a script supplied in a filter value was not executed by the page
 */
Given('the script from the Monitoring QR filter should not have run', () => {
    cy.window().should((win) => {
        expect(win.mqInjected).to.equal(undefined)
    })
})

/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I set the Monitoring QR filter {string} to {string}
 * @param {string} id - id of the dropdown
 * @param {string} value - option value
 * @description Selects an option in a filter dropdown, which reloads the page
 */
Given("I set the Monitoring QR filter {string} to {string}", (id, value) => {
    cy.get(`#${id}`).should('not.be.disabled').select(value)
    cy.location('search').should('include', `${id}=${value}`)
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I reset the Monitoring QR filters
 * @description Clicks the reset button on the filter form
 */
Given("I reset the Monitoring QR filters", () => {
    cy.document().its('readyState').should('eq', 'complete')
    cy.contains('button.clear-button', 'reset').then(($button) => $button[0].click())
    cy.location('search').should('not.include', 'defaulttimefilter=')
})


/**
 * @module ExternalModule
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I close the dialog box for the external module {string}
 * @param {string} name - Name of external module
 * @description Close the dialog box for the external module
 */
Given("I close the dialog box for the external module {string}", (name) => {
    cy.get('.modal-dialog').contains(name).parents('div[class="modal-header"]').within(() => {
        cy.get('button[class=close]').click()
    })
})


/**
 * @module Versioning/MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I should see the field labeled {string} disabled
 * @param {string} field_name - name of field
 * @description Verify field is disabled
 */
Given("I should see the field labeled {string} disabled", (field_name) => {
    let contains = ''
    let last_label = field_name
    field_name.split(' ').forEach((val) => {
        contains += `:has(:contains(${JSON.stringify(val)}))`
        last_label = val
    })
    let outer_element = `tr${contains}:has(input[type=text]):visible:first`

    cy.get(outer_element).within(() => {
        cy.get(`label:contains(${JSON.stringify(last_label)})`)
            .invoke('attr', 'id')
            .then(($id) => {
                let elm = cy.get('[name="' + $id.split('label-')[1] + '"]')
                elm.should('have.attr', 'disabled')
            })
    })
})


/**
 * @module EmbellishFields/MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I should see {string} within the data entry field labeled {string}
 * @param {string} fieldOptions - field options visible
 * @param {string} label - Field Label
 * @description verifies data entry field contains text
 */
Given("I should see {string} within the data entry field labeled {string}", (fieldOptions, label) => {
    cy.get('#questiontable').find('td').contains(label).parents('tr').should('contain', fieldOptions)
})


/**
 * @module MonitoringQR
 * @author Mintoo Xavier <min2xavier@gmail.com>
 * @example I should see {int} row(s) in the {emTableName} table
 * @param {int} num - number of row(s)
 * @param {string} emTableName - available options: 'monitoring logging'
 * @description verifies the table contains the specified number of row(s)
 */
Given('I should see {int} row(s) in the {emTableName} table', (num, tableName) => {
    const element = emTableName[tableName] + ' tbody tr'
    cy.get(element).its('length').then ((rowCount) => {
        // Subtracting 1 for header and dividing by 2 as each entry has 2 rows
        rowCount = (rowCount - 1) / 2

        expect(rowCount).to.be.equal(num)
    })
})
