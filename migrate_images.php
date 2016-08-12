<?php
$hostname="localhost";  
$username="root";   
$password="p";  

$d6_db = "mjd6";  
$d6 = new PDO("mysql:host=$hostname;dbname=$d6_db", $username, $password);  

$wp_db = "pantheon_wp";  
$wp = new PDO("mysql:host=$hostname;dbname=$wp_db", $username, $password);  

$wp_2 = new PDO("mysql:host=$hostname;dbname=$wp_db", $username, $password);  

//for master images
$master_data = $d6->prepare('
SELECT DISTINCT 
n.nid,
n.uid,
n.created,
n.changed,
n.status,
i.field_master_image_data,
c.field_master_image_caption_value,
b.field_art_byline_value,
s.field_suppress_master_image_value
FROM mjd6.node n
INNER JOIN mjd6.content_field_master_image i
USING(vid)
INNER JOIN mjd6.content_field_master_image_caption c
USING(vid)
INNER JOIN mjd6.content_field_art_byline b
USING(vid)
INNER JOIN mjd6.content_field_suppress_master_image s
USING(vid)
;
');
$master_data->execute();

$master_insert = $wp->prepare('
INSERT IGNORE INTO pantheon_wp.wp_posts
(post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
post_name, to_ping, pinged, post_modified, post_modified_gmt,
post_content_filtered, post_type, `post_status`, post_mime_type)
VALUES (
:post_author `post_author`,
FROM_UNIXTIME(:post_date) `post_date`,
FROM_UNIXTIME(:post_date) `post_date_gmt`,
"" `post_content`,
:post_title `post_title`,
"" `post_excerpt`,
:post_title `post_name`,
"" `to_ping`,
"" `pinged`,
FROM_UNIXTIME(:post_modified) `post_modified`,
FROM_UNIXTIME(:post_modified) `post_modified_gmt`,
"" `post_content_filtered`,
"attachment" `post_type`,
IF(:status = 1, "publish", "private") `post_status`,
:post_mime_type `post_mime_type`
)
;
');

$master_meta_insert = $wp_2->prepare('
INSERT IGNORE INTO pantheon_wp.wp_postmeta
VALUES (:post_id, "master_image", :meta_value)
;
');
$wp->beginTransaction();
$wp_2->beginTransaction();
while ( $master = $master_data->fetch(PDO::FETCH_ASSOC)) {
  $master_data_array = unserialize($master['field_master_image_data']);
  $mime_type = "image/" . preg_replace(
    '^.*\.',
    '',
    $master_data_array['title']
  );

  $master_insert->execute(array(
    ':post_author' => $master['uid'],
    ':post_date' => $master['created'],
    ':post_title' => $master_data_array['title'],
    ':post_modified' => $master['changed'],
    ':status' => $master['status'],
    ':post_mime_type' =>$mime_type
  ) );

  $master_meta_value = serialize( array(
    'master_image' => $wp->lastInsertId(),
    'master_image_byline' => $master['field_art_byline_value'],
    'master_image_caption' => $master['content_field_master_image_caption'],
    'master_image_suppress' => $master['field_suppress_master_image_value']
  ) );
  $master_meta_insert->execute(array(
    ':post_id' => $master['nid'],
    ':meta_value' => $master_meta_value
  ) );
}
$wp->commit();
$wp_2->commit();
?>
