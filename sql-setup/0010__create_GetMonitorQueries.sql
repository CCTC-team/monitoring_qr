/*
    A procedure that provides paged data for viewing the monitoring queries for a project
*/

create procedure GetMonitorQueries
    (
        in monQueryFieldNameSuffix varchar(100) collate utf8mb4_unicode_ci,
        in skipCount int,
        in pageSize int,
        in projectId int,
        in retDirection varchar(4),
        in recordId varchar(10),
        in minDate bigint,
        in maxDate bigint,
        in currentQueryStatus varchar(10),    -- OPEN or CLOSED or 'not opened' - negate with leading '^'
        in eventId int,
        in instance smallint(4),
        in formName varchar(100) collate utf8mb4_unicode_ci,
        in fieldName varchar(100) collate utf8mb4_unicode_ci,
        in flag varchar(100) collate utf8mb4_unicode_ci,
        in response varchar(100) collate utf8mb4_unicode_ci,
        in queryText varchar(100) collate utf8mb4_unicode_ci,    -- uses 'like'
        in currentMonitorStatus smallint(4),        -- int value for monstat field
        in notOpenedText varchar(100),              -- e.g. NONE
        in requiresVerificationIndex smallint,       -- int value for monstat field meaning 'requires verification'
        in alwaysIncludeWhenNoTimestamp smallint,    -- when not zero, include items with no timestamp regardless of date filters
        in userName varchar(100) collate utf8mb4_unicode_ci,
        in dagUser varchar(100) collate utf8mb4_unicode_ci     -- if given, must restrict to this user, if null get any
    )
begin

    -- the dagUser indicates whether the logs should be restricted to membership of a dag
    -- if the user is in a dag, this will not be null. If not null restrict the data for the user


declare mess mediumtext;
declare sqlQuery mediumtext;

if monQueryFieldNameSuffix is null or monQueryFieldNameSuffix = '' then
    set mess = concat('The stored proc GetMonitorQueries can''t proceed without a monQueryFieldNameSuffix being provided');
    signal sqlstate '45000' set message_text = mess;
end if;

if retDirection is null or retDirection = '' then
    set retDirection = 'desc';
end if;

-- the currentQueryStatus can be negated with leading '^' e.g. ^OPEN means any status but open
if currentQueryStatus is not null and not
    (currentQueryStatus = 'OPEN' or currentQueryStatus = '^OPEN'
    or currentQueryStatus = 'CLOSED' or currentQueryStatus = '^CLOSED'
    or currentQueryStatus = notOpenedText or currentQueryStatus = concat('^', notOpenedText)) then
    set mess =
        concat('The only valid values for currentQueryStatus are null, OPEN, ^OPEN, CLOSED, ^CLOSED, ', notOpenedText, 'or ^', notOpenedText);
    signal sqlstate '45000' set message_text = mess;
end if;

-- the currentMonitorStatus can be negated by adding 90 i.e. 1 matches 'Verified', whereas -1 matches not verified
-- note: 0 is not a valid value as null is used instead
if currentMonitorStatus is not null and (currentMonitorStatus < -5 or currentMonitorStatus > 5 or currentMonitorStatus = 0) then
    set mess = concat('Valid values for currentMonitorStatus are null, or a value between -5 and 5 inclusive. 0 is not valid - use null instead');
    signal sqlstate '45000' set message_text = mess;
end if;

-- create the table for results so can get the options for filters
drop table if exists rh_mon_queries;
create temporary table rh_mon_queries
(
    urn mediumint NOT NULL auto_increment PRIMARY KEY,
    ts bigint(14) DEFAULT NULL,
    record varchar(100) DEFAULT NULL,
    event_id int(10) DEFAULT NULL,
    event_name varchar(64) DEFAULT NULL,
    field_name varchar(255) DEFAULT NULL,
    instance int DEFAULT NULL,
    comment text DEFAULT NULL,
    current_query_status varchar(100) DEFAULT NULL,
    username varchar(100) DEFAULT NULL,
    form_name varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

set sqlQuery = concat(
    'insert into rh_mon_queries (ts, record, event_id, event_name, field_name, instance, comment,
        current_query_status, username, form_name)
    with rankedEntry as
    (
        select
            b.ts,
            a.record,
            a.event_id,
            a.field_name,
            a.instance,
            b.comment,
            b.current_query_status,
            d.username,
            ROW_NUMBER() OVER (PARTITION BY a.record, a.event_id, a.field_name, a.instance ORDER BY b.ts DESC) as rn
        from
            redcap_data_quality_status a
            inner join redcap_data_quality_resolutions b
            on a.status_id = b.status_id
            inner join redcap.redcap_user_information d
            on b.user_id = d.ui_id

            -- check for dag membership
            inner join
                (
                    select distinct
                        w.project_id,
                        w.record,
                        y.username,
                        x.group_id,
                        z.group_name
                    from
                        redcap_data w
                        left outer join
                        (
                            select project_id, value as group_id, record from redcap_data
                            where project_id = ? and field_name = ''__GROUPID__''
                        ) x
                        on w.project_id = x.project_id
                        and w.record = x.record
                        left join
                        (
                            -- gets the group_id for the user from either source for this
                            select distinct project_id, group_id, username from redcap_data_access_groups_users
                            where project_id = ?
                            union
                            select project_id, group_id, username from redcap_user_rights
                            where project_id = ?
                            and group_id is not null
                        ) y
                        on x.project_id = y.project_id
                        and x.group_id = y.group_id
                        left join redcap_data_access_groups z
                        on y.project_id = z.project_id
                        and y.group_id = z.group_id
                    where
                        w.project_id = ?
                ) c
                on a.record = c.record
                where
                    a.project_id = ?
                    and a.field_name like concat(''%'', ?)
                    and ? is null or ? = c.username     -- daguser is null or must filter for it when given
    ),
    joined as (
        select distinct
            ts,
            record,
            a.event_id,
            c.descrip as event_name,
            a.field_name,
            instance,
            comment,
            current_query_status,
            username,
            b.form_name
        from
            rankedEntry a
            inner join redcap_metadata b
            on a.field_name = b.field_name
            inner join redcap_events_metadata c
            on a.event_id = c.event_id
        where
            -- ranked 1 i.e. latest record
            a.rn = 1
    )
    select
        ts,
        record,
        event_id,
        event_name,
        field_name,
        instance,
        comment,
        current_query_status,
        username,
        form_name
    from
        joined
    union all
        (
        select
            null as ts,
            a.record,
            a.event_id,
            c.descrip as event_name,
            a.field_name,
            IF(a.instance is null, 1, a.instance) as instance,
            null as comment,
            ? as current_query_status,
            null as username,
            b.form_name
        from
            redcap_data a
            inner join redcap_metadata b
            on
                a.project_id = b.project_id
                and a.field_name = b.field_name
            inner join redcap_events_metadata c
            on a.event_id = c.event_id
        where
            a.project_id = ?
            and a.field_name  like concat(''%'', ?)
            and a.value = ?                                      -- param
            and not exists
                (
                    select null
                    from
                        joined p
                    where
                        a.record = p.record
                        and a.event_id = p.event_id
                        and a.field_name = p.field_name
                        and IF(a.instance is null, 1, a.instance) = p.instance
                )
        )
    order by
        ts ', retDirection,
        ', record'
    );

    prepare qry from sqlQuery;
    execute qry using
        projectId, projectId, projectId, projectId,
        projectId,
        monQueryFieldNameSuffix,
        dagUser, dagUser,
        notOpenedText,
        projectId,
        monQueryFieldNameSuffix,
        requiresVerificationIndex
    ;
    deallocate prepare qry;

    -- create final table so can page against it and get correct counts
    drop table if exists mon_queries_final;
    create temporary table mon_queries_final
    (
        urn mediumint NOT NULL auto_increment PRIMARY KEY,
        ts bigint(14) DEFAULT NULL,
        record varchar(100) DEFAULT NULL,
        event_id int(10) DEFAULT NULL,
        event_name varchar(64) DEFAULT NULL,
        field_name varchar(255) DEFAULT NULL,
        instance int DEFAULT NULL,
        comment text DEFAULT NULL,
        current_query_status varchar(100) DEFAULT NULL,
        username varchar(100) DEFAULT NULL,
        form_name varchar(100) DEFAULT NULL,
        mon_stat_value varchar(100) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- return the main record set applying the paging here
    set sqlQuery =
        'insert into mon_queries_final (urn, ts, record, event_id, event_name, field_name, instance, comment,
            current_query_status, username, form_name, mon_stat_value)
         with mon_queries as
         (select * from rh_mon_queries
            where
                (? is null or record = ?)
                -- minDate - take into account whether to include items with null timestamp
                and ((? is null and ((? <> 0 and ts is null) or ts is not null)) or (ts >= ? or (? <> 0 and ts is null)))
                -- maxDate - take into account whether to include items with null timestamp
                and ((? is null and ((? <> 0 and ts is null) or ts is not null)) or (ts <= ? or (? <> 0 and ts is null)))
                -- current query status - if first char is ^ then negate the query
                and (? is null or (case when left(?, 1) = ''^'' then current_query_status <> substring(?, 2) else current_query_status = ? end))
                -- event id
                and (? is null or event_id = ?)
                -- instance
                and (? is null or instance = ?)
                -- form
                and (? is null or form_name = ?)
                -- field name
                and (? is null or json_extract(comment, ''$[*].field'')  like concat(''%"'', ?, ''"%''))
                -- flag
                and (? is null or json_extract(comment, ''$[*].flags'')  like concat(''%'', ?, ''%''))
                -- username
                and (? is null or username = ?)
                -- response
                -- and (? is null or json_extract(comment, ''$[*].response'')  like concat(''%"'', ?, ''"%''))
                and (? is null or
                    (case when ? = ''no response''  then
                     -- only include where there is a query in the json, but not a response
                     json_extract(comment, ''$[*].response'')  is null and
                     json_extract(comment, ''$[*].query'')  is not null
                     else json_extract(comment, ''$[*].response'')  like concat(''%'', ?, ''%'') end)
                    )
                -- query
                and (? is null or json_extract(comment, ''$[*].query'')  like concat(''%"%'', ?, ''%"%''))
          order by urn
          )
            select
                a.*,
                REGEXP_SUBSTR(c.element_enum, concat(b.value, '', [0-9a-zA-Z _]+'')) as mon_stat_value
            from
                mon_queries a
                inner join redcap_data b
                on
                    a.event_id = b.event_id
                    and a.record = b.record
                    -- inconsistency in how instance is stored need to account for use of null equalling 1
                    and a.instance = IF(b.instance is null, 1, b.instance)
                    and a.field_name = b.field_name
                inner join redcap_metadata c
                on
                    b.project_id = c.project_id
                    and b.field_name = c.field_name
            where
                b.project_id = ?
                -- current monitor status - negative numbers are used to negate the selection
                and (? is null or (case when ? < 0 then b.value <> abs(?) else b.value = ? end))
                 ;';

    prepare qry from sqlQuery;
    execute qry using
        recordId, recordId,
        minDate, alwaysIncludeWhenNoTimestamp, minDate, alwaysIncludeWhenNoTimestamp,
        maxDate, alwaysIncludeWhenNoTimestamp, maxDate, alwaysIncludeWhenNoTimestamp,
        currentQueryStatus, currentQueryStatus, currentQueryStatus, currentQueryStatus,
        eventId, eventId,
        instance, instance,
        formName, formName,
        fieldName, fieldName,
        flag, flag,
        userName, userName,
        response, response, response,
        queryText, queryText,
        projectId,
        currentMonitorStatus, currentMonitorStatus, currentMonitorStatus, currentMonitorStatus;
    deallocate prepare qry;

    -- return total count
    select count(*) as total_count from mon_queries_final;

    -- event id
    select distinct event_id, event_name from rh_mon_queries order by event_id;

    -- return distinct instances
    -- note: the rh_mon_queries prefix for the column is required for correct return
    select distinct rh_mon_queries.instance from rh_mon_queries order by instance;

    -- form name
    select distinct form_name from rh_mon_queries order by form_name;

    -- field name
    select distinct json_extract(comment, '$[*].field') as fieldArr
    from rh_mon_queries
    where json_extract(comment, '$[*].field') is not null;

    -- flag
    select distinct json_extract(comment, '$[*].flags') as flagArr
    from rh_mon_queries
    where json_extract(comment, '$[*].flags') is not null;

    -- response
    select distinct json_extract(comment, '$[*].response') as responseArr
    from rh_mon_queries
    where json_extract(comment, '$[*].response') is not null;

    -- username
    select distinct rh_mon_queries.username from rh_mon_queries order by username;

    set sqlQuery =
        concat('select * from mon_queries_final order by urn limit ', pageSize, ' offset ', skipCount, ';');

    prepare qry from sqlQuery;
    execute qry;

end;


# call getmonitorqueries('_monstat',0, 100, 16, 'desc',
# null, null, null, null, null, null, null,
#         null, null, null, null, null,
#         null, 2, 1, null, 'test_de1');
