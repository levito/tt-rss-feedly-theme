#!/bin/sh

export PGPASSWORD=test

psql -h localhost -q -U test test < schema/ttrss_schema_pgsql.sql
