begin;

ALTER TABLE ttrss_counters_cache ENGINE=InnoDB DEFAULT CHARSET=UTF8;
ALTER TABLE ttrss_cat_counters_cache ENGINE=InnoDB DEFAULT CHARSET=UTF8;
ALTER TABLE ttrss_feedbrowser_cache ENGINE=InnoDB DEFAULT CHARSET=UTF8;

update ttrss_version set schema_version = 123;

commit;
