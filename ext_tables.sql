#
# Table structure for table 'tx_pongback_domain_model_pingback'
#
CREATE TABLE tx_pongback_domain_model_pingback
(
    source_link            varchar(255) DEFAULT '' NOT NULL,
    ping_client            varchar(255) DEFAULT '' NOT NULL,
    ping_resource          varchar(255) DEFAULT '' NOT NULL,
    target_link            varchar(255) DEFAULT '' NOT NULL,
    serialized_information text,
);
