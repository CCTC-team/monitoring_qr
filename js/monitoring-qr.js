/**
 * Monitoring QR Module - Common JavaScript Functions
 *
 * Note: MonQR constants object must be defined before this file is loaded.
 * The constants are injected by PHP in MonitoringQRModule.php
 */

// Adds an inline monitor query notification
function highlightFieldIfMonitored(field, query) {
    let elem = document.querySelector('#label-' + field);
    if(elem) {
        const monSpan = document.createElement('span');
        monSpan.setAttribute('style', 'padding: 2px; margin-left: 5px; font-weight: normal');

        const icon = document.createElement('i');
        icon.classList.add('fas', 'fa-flag', 'mr-3');
        icon.setAttribute('style', 'color: blue');
        const mess = document.createElement('span');
        mess.setAttribute('style', 'color: blue');
        let q = query;
        if(query.length > 50) {
            q = query.substring(0, 47) + '...';
        }
        mess.textContent = q;

        monSpan.appendChild(icon);
        monSpan.appendChild(mess);
        elem.insertAdjacentElement('beforeend', monSpan);
    }
}

// Changes the monitor status, updates the ui and writes to the db
// Reload is required to update ui
function changeMonitoringStatus(ajaxPath, projectId, eventId, record, field, newStatus, instance, instrument, showProgressSpinner = false) {

    let monField = document.querySelector('[name=' + field + ']');
    monField.value = newStatus;

    if(showProgressSpinner) {
        showProgress(1);
    }

    // Run the ajax query to update the db
    $.post(ajaxPath,
    {
        projectId: projectId,
        eventId: eventId,
        record: record,
        monitorField: field,
        statusInt: newStatus,
        repeatInstance : instance,
        instrument : instrument
    }, function (data) {
        console.log('data ' + data.result);
    })

    // Reloads the page to refresh ui
    window.location.reload();
}

// Writes the query and optionally updates the monitoring status
function writeQueryAndChangeStatus(
    ajaxPath, allQueries, field, pid, instance,
    event_id, record, reopen, status, send_back,
    newIndex, response, response_requested, instrument,
    changeMonStatFunc) {

    if(allQueries === 'query closed as verified') {

        const fieldComments = document.querySelectorAll('[id^="mon-q-response-comment"]');
        let notResponded = false;
        fieldComments.forEach(function(fieldComment) {
            if(fieldComment.textContent === 'No response')
                notResponded = true;
        });

        if(notResponded) {
            alert('You cannot verify a query that has no response.');
            return;
        }
    }

    showProgress(1);

    $.post(app_path_webroot+'DataQuality/data_resolution_popup.php?pid='+pid+'&instance='+instance,
        { action: 'save', field_name: field, event_id: event_id, record: record,
        comment: allQueries,
        response_requested: response_requested,
        upload_doc_id: null,
        delete_doc_id: null,
        assigned_user_id: null,
        assigned_user_id_notify_email: 0,
        assigned_user_id_notify_messenger: 0,
        status: status,
        send_back: send_back,
        response: response,
        reopen_query: reopen,
        rule_id: null
    }, function(data) {
        if (data === '0') {
            alert(woops);
        } else {
            // Set the status of the query if function given
            if(changeMonStatFunc) {
                changeMonStatFunc(ajaxPath, pid, event_id, record, field, newIndex, instance, instrument);
            }
        }
    })
}

// Hides the monitoring field icons
function hideMonitoringStatusField(monitorField) {
    document.querySelector('#' + monitorField + '-tr').classList.add('@HIDDEN');
}

// Hides the cancel button row when it's not applicable to the role logged in
function hideCancelButtonRow() {
    let cancelButtonRow = document.querySelector('[value=\'-- Cancel --\']');
    if(cancelButtonRow) {
        cancelButtonRow.parentElement.parentElement.parentElement;
        cancelButtonRow.style.display = 'none';
    }
}

// Makes specified fields readonly, excluding monitor field and _complete field
function makeFieldsReadonly(fields, monitorField) {
    fields.forEach(function(field) {
        if(field !== monitorField && !field.endsWith('_complete')) {
            document.querySelector('#' + field + '-tr').classList.add('@READONLY');
        }
    })
}

// Shows the monitor query status - whether query on monitor field is open or closed
// Also shows the actual monitor query value - e.g. verified, requires verification due to data change etc.
function showMonitoringStatus(currentQueryStatus, monStatusValue) {

    let elem = document.querySelector('.formtbody');
    if(elem) {
        const tr = document.createElement('tr');

        tr.classList.add('labelrc');
        const td1 = document.createElement('td');
        td1.setAttribute('style', 'padding-top: 10px; padding-bottom: 10px;');
        const monSpan = document.createElement('span');
        monSpan.setAttribute('style', 'padding: 2px; margin-left: 5px; font-weight: normal; margin-top: 5px;');
        const mess = document.createElement('span');

        let col = 'blue';
        if(currentQueryStatus === MonQR.QUERY_CLOSED) {
            col = 'green';
        }

        mess.setAttribute('style', 'color: ' + col + ';');
        mess.textContent = 'Monitor query status: ' + currentQueryStatus;

        const icon = document.createElement('i');
        icon.classList.add('fas', 'fa-flag', 'mr-3');
        icon.setAttribute('style', 'color: ' + col + ';');

        monSpan.appendChild(icon);
        monSpan.appendChild(mess);
        td1.appendChild(monSpan);
        tr.appendChild(td1);

        const td2 = document.createElement('td');
        const monSpan2 = document.createElement('span');
        const icon2 = document.createElement('i');
        icon2.classList.add('fas', 'fa-clipboard-question', 'mr-3');

        let valCol = 'blue';
        if(monStatusValue === 'Verified' || monStatusValue === 'Not required') {
            valCol = 'green';
        }

        icon2.setAttribute('style', 'color: ' + valCol + ';');
        const mess2 = document.createElement('span');
        mess2.textContent = monStatusValue;
        td2.setAttribute('style', 'color: ' + valCol + '; font-weight: normal');

        // Fix to stop the icon showing on its own when there is no resolution status
        if(monStatusValue) {
            monSpan2.appendChild(icon2);
            monSpan2.appendChild(mess2);
        }

        // Need to still append the td2 to keep the style consistent
        td2.appendChild(monSpan2);
        tr.appendChild(td2);

        // Add a monitoring section header
        const monSectionHeaderRow = document.createElement('tr');
        const monSectHeaderCell = document.createElement('td');
        monSectHeaderCell.className = 'header';
        monSectHeaderCell.setAttribute('colspan', '2');
        // Create the inner div
        const div = document.createElement('div');
        div.setAttribute('data-mlm-type', 'header');
        div.textContent = 'Form Status';
        // Append div to td
        monSectHeaderCell.appendChild(div);
        monSectHeaderCell.textContent = 'Monitoring Status';

        // Add header
        monSectionHeaderRow.appendChild(monSectHeaderCell);
        elem.insertAdjacentElement('beforeend', monSectionHeaderRow);

        // Add monitoring info
        elem.insertAdjacentElement('beforeend', tr);
    }
}

// Sets the monitoring status to the given value (note: newValue is id)
function setMonitoringStatus(monitorField, newValue) {

    // Change the status
    let sel = '#' + monitorField + '-tr select';
    let ele = document.querySelector(sel);
    ele.value = newValue;

    // Alert the user
    const userAlert = document.createElement('small');
    userAlert.classList = 'text-success';
    userAlert.innerHTML = '<div>status set automatically to ' + newValue + '</div>';

    ele.insertAdjacentElement('afterend', userAlert);
}

// Toggles the history display
function showHistory() {
    let showHistButton = document.getElementById('show-hide-history-button');
    if(showHistButton.textContent === 'Show history') {
        document.getElementById('form-query-history').style.display = 'block';
        showHistButton.textContent = 'Hide history';
    } else {
        document.getElementById('form-query-history').style.display = 'none';
        showHistButton.textContent = 'Show history';
    }
}

// Adds the history button to the form
function addHistoryButton(showHistory) {
    document.addEventListener('DOMContentLoaded', function() {
        let elem = document.querySelector('.formtbody');
        if (elem) {
            // Create a new <td> element
            const tr = document.createElement('tr');
            tr.classList.add('labelrc');
            const td1 = document.createElement('td');
            td1.setAttribute('style', 'padding-top: 10px; padding-bottom: 10px;');
            const td2 = document.createElement('td');
            td2.setAttribute('style', 'padding-top: 10px; padding-bottom: 10px;');
            td2.innerHTML = showHistory;
            tr.appendChild(td1);
            tr.appendChild(td2);
            elem.appendChild(tr);
        }
    });
}
