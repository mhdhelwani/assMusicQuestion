<#1>
<?php
//Add Marker Question Type
$res = $ilDB->queryF("SELECT * FROM qpl_qst_type WHERE type_tag = %s",
    array("text"),
    array("assMusicQuestion")
);
if ($res->numRows() == 0) {
    $res = $ilDB->query("SELECT MAX(question_type_id) maxid FROM qpl_qst_type");
    $data = $ilDB->fetchAssoc($res);
    $max = $data["maxid"] + 1;

    $affectedRows = $ilDB->manipulateF("INSERT INTO qpl_qst_type (question_type_id, type_tag, plugin) VALUES (%s, %s, %s)",
        array("integer", "text", "integer"),
        array($max, "assMusicQuestion", 1)
    );
}
?>
<#2>
<?php
//Options
$fields = array(
    "question_fi" => array("type" => "integer", "length" => 4, "notnull" => true),
    "keys_value" => array("type" => "text", "length" => 1000, "notnull" => true)

);
$ilDB->createTable("il_qpl_qst_music_data", $fields);
$ilDB->addPrimaryKey("il_qpl_qst_music_data", array("question_fi"));
?>