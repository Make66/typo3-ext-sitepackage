# gridelements to container; use container default CTypes
UPDATE tt_content
SET CType = 'container_2_columns'
WHERE CType = 'gridelements_pi1'
  AND tx_gridelements_backend_layout = '2cols';

UPDATE tt_content
SET CType = 'container_3_columns'
WHERE CType = 'gridelements_pi1'
  AND tx_gridelements_backend_layout = '3cols';

UPDATE tt_content
SET CType = 'container_4_columns'
WHERE CType = 'gridelements_pi1'
  AND tx_gridelements_backend_layout = '4cols';

# reconnect gridelements childs to container columns; bootstrap_package columns start with 200 while gridelement columns start with 100
UPDATE tt_content t1
    LEFT JOIN tt_content t2
    ON t2.uid = t1.tx_gridelements_container
SET t1.colPos              = IF(t1.tx_gridelements_columns = 0, 200, t1.tx_gridelements_columns + 100),
    t1.tx_container_parent = t1.tx_gridelements_container
WHERE t1.tx_gridelements_container != 0;

# cleanup gridelements
ALTER table tt_content
    DROP tx_gridelements_backend_layout;
ALTER table tt_content
    DROP tx_gridelements_columns;
ALTER table tt_content
    DROP tx_gridelements_container;