CREATE TABLE banana_subpage (
    page_namespace int NOT NULL,
    page_title varbinary(255) NOT NULL,
    parent_id int unsigned NOT NULL,
    subpage_order smallint unsigned NOT NULL,
    subpage_number tinyblob,
    display_title blob,
    PRIMARY KEY (page_namespace, page_title),
    FOREIGN KEY (parent_id) REFERENCES page (page_id) ON DELETE CASCADE
) DEFAULT CHARSET binary;

CREATE INDEX idx_parent_id ON banana_subpage (parent_id);

CREATE TABLE banana_label (
    id int unsigned NOT NULL AUTO_INCREMENT,
    page_id int unsigned NOT NULL,
    label_name varbinary(255),
    label_target varbinary(255),
    label_text blob,
    PRIMARY KEY (id),
    FOREIGN KEY (page_id) REFERENCES page (page_id) ON DELETE CASCADE
) DEFAULT CHARSET binary;

CREATE INDEX idx_page_id ON banana_label (page_id);

CREATE TABLE banana_cache (
    md5 binary(32) NOT NULL,
    result blob NOT NULL,
    last_used binary(14) NOT NULL,
    PRIMARY KEY (md5)
) DEFAULT CHARSET binary;

CREATE INDEX idx_last_used ON banana_cache (last_used);
