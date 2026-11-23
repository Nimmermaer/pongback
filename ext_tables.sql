#
# Table structure for table 'tx_pongback_domain_model_pingback'
#
CREATE TABLE tx_pongback_domain_model_pingback
(
    source_link  varchar(255) DEFAULT '' NOT NULL,
    source_title varchar(255) DEFAULT '' NOT NULL,
    target_link  varchar(255) DEFAULT '' NOT NULL,
    excerpt      text,
);
