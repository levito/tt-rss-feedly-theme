create function SUBSTRING_FOR_DATE(timestamp, int, int) RETURNS text AS 'SELECT SUBSTRING(CAST($1 AS text), $2, $3)' LANGUAGE 'sql';

update ttrss_version set schema_version = 35;
