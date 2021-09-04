<?php

namespace phpListRestapi;

defined('PHPLISTINIT') || die;

/**
 * Class Campaigns
 * Manage phplist Campaigns.
 */
class Campaigns
{
    public static function campaignGet($id = 0)
    {
        if ($id == 0) {
            $id = $_REQUEST['id'];
        }

       $response = new Response();
       try {
            $db = PDO::getConnection();
            $sql = 'SELECT * FROM '.$GLOBALS['tables']['message'].' WHERE id=:id;';
            $stmt = $db->prepare($sql);
            $stmt->bindParam('id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_OBJ);
            $result = $result[0];

            // Add fields from the messagedata table
            $sql = 'SELECT name, data FROM '.$GLOBALS['tables']['messagedata'].' WHERE id=:id;';
            $stmt = $db->prepare($sql);
            $stmt->bindParam('id', $id, PDO::PARAM_INT);
            $stmt->execute();

            foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $obj) {
                $name = $obj->name;
                $result->$name = $obj->data;
            }
            $response->setData('Campaign', $result);
            $response->output();
        } catch (\Exception $e) {
            Response::outputError($e);
        }
    }

    public static function campaignsCount()
    {
        Common::select('Campaign', 'SELECT count(id) as total FROM '.$GLOBALS['tables']['message'],array(),true);
    }


    /**
     * Get all the Campaigns in the system.
     *
     * <p><strong>Parameters:</strong><br/>
     * [order_by] {string} name of column to sort, default "id".<br/>
     * [order] {string} sort order asc or desc, default: asc.<br/>
     * [limit] {integer} limit the result, default 10 (max 10)<br/>
     * [offset] {integer} offset of the result, default 0.<br/>
     * </p>
     * <p><strong>Returns:</strong><br/>
     * List of Campaigns.
     * </p>
     */
    public static function campaignsGet($order_by = 'modified', $order = 'desc', $limit = 10, $offset = 0)
    {
        if (isset($_REQUEST['order_by']) && !empty($_REQUEST['order_by'])) {

            $order_by = $_REQUEST['order_by'];
            $order_by =  preg_replace('/[^a-zA-Z0-9_$]/', '', $order_by);

            if (isset($_REQUEST['order']) && !empty($_REQUEST['order']) &&
                (strtolower($_REQUEST['order'] == 'asc') || strtolower($_REQUEST['order'] == 'desc'))) {
                $order = $_REQUEST['order'];
            }

        }

        if (isset($_REQUEST['limit']) && !empty($_REQUEST['limit'])) {
            $limit = intval($_REQUEST['limit']);
        }
        if (isset($_REQUEST['offset']) && !empty($_REQUEST['offset'])) {
            $offset = intval($_REQUEST['offset']);
        }
        if ($limit > 10) {
            $limit = 10;
        }

        $subsql = "SELECT COUNT(userid) FROM " . $GLOBALS['tables']['usermessage'] . " WHERE viewed IS NOT NULL AND status = 'sent' and messageid = m.id";
        $sublists = "SELECT GROUP_CONCAT(`listid` SEPARATOR ',') from ".$GLOBALS['tables']['listmessage']." WHERE messageid = m.id";

        $sql  = "SELECT COALESCE(SUM(clicked), 0) AS clicked, (" . $subsql . ") AS uviews, ";
        $sql .= "(".$sublists.") as lists ,";
        $sql .= "m.* ";
        $sql .= "FROM " . $GLOBALS['tables']['message'] . " AS m ";
        $sql .= "LEFT JOIN " . $GLOBALS['tables']['linktrack_uml_click'] . " AS c ON ( c.messageid = m.id ) ";
        $sql .= "GROUP BY m.id ";
        $sql .= "ORDER BY $order_by $order ";
        $sql .= "LIMIT :limit OFFSET :offset ";

        $params = array (
            'limit' => array($limit,PDO::PARAM_INT),
            'offset' => array($offset,PDO::PARAM_INT),
        );

        Common::select('Campaigns', $sql, $params);
    }

    /**
     * Add a new campaign.
     *
     * <p><strong>Parameters:</strong><br/>
     * [*subject] {string} <br/>
     * [*fromfield] {string} <br/>
     * [*replyto] {string} <br/>
     * [*message] {string} <br/>
     * [*textmessage] {string} <br/>
     * [*footer] {string} <br/>
     * [*status] {string} <br/>
     * [*sendformat] {string} <br/>
     * [*template] {string} <br/>
     * [*embargo] {string} <br/>
     * [*rsstemplate] {string} <br/>
     * [*owner] {string} <br/>
     * [htmlformatted] {string} <br/>
     * <p><strong>Returns:</strong><br/>
     * The message added.
     * </p>
     */
    public static function campaignAdd()
    {
        $sql1 = <<<END
    SELECT id
    FROM {$GLOBALS['tables']['message']}
    WHERE subject = :subject AND template = :template AND status IN ('submitted', 'draft')
    LIMIT 1
END;
        $sql2 = <<<END
        INSERT INTO {$GLOBALS['tables']['message']}
        (subject, fromfield, replyto, message, textmessage, footer, entered, status, sendformat, template, embargo, rsstemplate, owner, htmlformatted, uuid )
        VALUES (:subject, :fromfield, :replyto, :message, :textmessage, :footer, now(), :status, :sendformat, :template, :embargo, :rsstemplate, :owner, :htmlformatted, :uuid);
END;
        try {
            $db = PDO::getConnection();
            $stmt = $db->prepare($sql1);
            $stmt->bindParam('subject', $_REQUEST['subject'], PDO::PARAM_STR);
            $stmt->bindParam('template', $_REQUEST['template'], PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_OBJ);

            if ($result !== false) {
                Response::outputErrorMessage('duplicate');
            }
            $stmt = $db->prepare($sql2);
            $stmt->bindParam('subject', $_REQUEST['subject'], PDO::PARAM_STR);
            $stmt->bindParam('fromfield', $_REQUEST['fromfield'], PDO::PARAM_STR);
            $stmt->bindParam('replyto', $_REQUEST['replyto'], PDO::PARAM_STR);
            $stmt->bindParam('message', $_REQUEST['message'], PDO::PARAM_STR);
            $stmt->bindParam('textmessage', $_REQUEST['textmessage'], PDO::PARAM_STR);
            $stmt->bindParam('footer', $_REQUEST['footer'], PDO::PARAM_STR);
            $stmt->bindParam('status', $_REQUEST['status'], PDO::PARAM_STR);
            $stmt->bindParam('sendformat', $_REQUEST['sendformat'], PDO::PARAM_STR);
            $stmt->bindParam('template', $_REQUEST['template'], PDO::PARAM_INT);
            $stmt->bindParam('embargo', $_REQUEST['embargo'], PDO::PARAM_STR);
            $stmt->bindParam('rsstemplate', $_REQUEST['rsstemplate'], PDO::PARAM_STR);
            $stmt->bindParam('owner',  $_SESSION['logindetails']['id'], PDO::PARAM_INT);
            $stmt->bindParam('htmlformatted', $_REQUEST['htmlformatted'], PDO::PARAM_STR);
            $uuid = (string) \UUID::generate(4);
            $stmt->bindParam('uuid', $uuid, PDO::PARAM_STR);
            $stmt->execute();
            $id = $db->lastInsertId();
            $db = null;
        } catch (\Exception $e) {
            Response::outputError($e);
        }

        // Add any extra fields to the messagedata table
        $fieldsToIgnore = [
            'subject', 'fromfield', 'replyto', 'message', 'textmessage', 'footer', 'status', 'sendformat', 'template',
            'embargo', 'rsstemplate', 'owner', 'htmlformatted', 'pi', 'cmd', 'page', 'secret',
        ];
        $extraFields = array_diff(array_keys($_REQUEST), $fieldsToIgnore);

        if (count($extraFields) > 0) {
            $sql = <<<"END"
INSERT INTO {$GLOBALS['tables']['messagedata']}
(name, id, data)
VALUES (:name, :id, :data)
END;
            try {
                $db = PDO::getConnection();
                $stmt = $db->prepare($sql);
                $stmt->bindParam('id', $id, PDO::PARAM_INT);

                foreach ($extraFields as $field) {
                    $stmt->bindParam('name', $field, PDO::PARAM_STR);
                    $stmt->bindParam('data', $_REQUEST[$field], PDO::PARAM_STR);
                    $stmt->execute();
                }
                $db = null;
            } catch (\Exception $e) {
                Response::outputError($e);
            }
        }

        return self::campaignGet($id);
    }

    /**
     * Update existing campaign.
     *
     * <p><strong>Parameters:</strong><br/>
     * [*id] {integer} <br/>
     * [*subject] {string} <br/>
     * [*fromfield] {string} <br/>
     * [*replyto] {string} <br/>
     * [*message] {string} <br/>
     * [*textmessage] {string} <br/>
     * [*footer] {string} <br/>
     * [*status] {string} <br/>
     * [*sendformat] {string} <br/>
     * [*template] {string} <br/>
     * [*embargo] {string} <br/>
     * [*rsstemplate] {string} <br/>
     * [owner] {string} <br/>
     * [htmlformatted] {string} <br/>
     * <p><strong>Returns:</strong><br/>
     * The message added.
     * </p>
     */
    public static function campaignUpdate($id = 0)
    {
        if ($id == 0) {
            $id = $_REQUEST['id'];
        }
        $sql = 'UPDATE '.$GLOBALS['tables']['message'].' SET subject=:subject, fromfield=:fromfield, replyto=:replyto, message=:message, textmessage=:textmessage, footer=:footer, status=:status, sendformat=:sendformat, template=:template, embargo=:embargo, rsstemplate=:rsstemplate, owner=:owner, htmlformatted=:htmlformatted WHERE id=:id;';
        try {
            $db = PDO::getConnection();
            $stmt = $db->prepare($sql);
            $stmt->bindParam('id', $id, PDO::PARAM_INT);
            $stmt->bindParam('subject', $_REQUEST['subject'], PDO::PARAM_STR);
            $stmt->bindParam('fromfield', $_REQUEST['fromfield'], PDO::PARAM_STR);
            $stmt->bindParam('replyto', $_REQUEST['replyto'], PDO::PARAM_STR);
            $stmt->bindParam('message', $_REQUEST['message'], PDO::PARAM_STR);
            $stmt->bindParam('textmessage', $_REQUEST['textmessage'], PDO::PARAM_STR);
            $stmt->bindParam('footer', $_REQUEST['footer'], PDO::PARAM_STR);
            $stmt->bindParam('status', $_REQUEST['status']);
            $stmt->bindParam('sendformat', $_REQUEST['sendformat'], PDO::PARAM_STR);
            $stmt->bindParam('template', $_REQUEST['template'], PDO::PARAM_INT);
            $stmt->bindParam('embargo', $_REQUEST['embargo'], PDO::PARAM_STR);
            $stmt->bindParam('rsstemplate', $_REQUEST['rsstemplate'], PDO::PARAM_STR);
            $stmt->bindParam('owner', $_REQUEST['owner'], PDO::PARAM_INT);
            $stmt->bindParam('htmlformatted', $_REQUEST['htmlformatted'], PDO::PARAM_BOOL);
            $stmt->execute();
            $db = null;
            self::campaignGet($id);
        } catch (\Exception $e) {
            Response::outputError($e);
        }
    }

    /**
     * Delete a campaign.
     *
     * <p><strong>Parameters:</strong><br/>
     * [*id] {integer} the ID of the campaign.
     * <p><strong>Returns:</strong><br/>
     * Campaign id for success otherwise a failure message.
     * </p>
     */
    public static function campaignDelete($id = 0)
    {
        if ($id == 0) {
            $id = $_REQUEST['id'];
        }

        $deleted = deleteMessage($id);
        if ($deleted > 0) {
            Response::outputDeleted('Campaign', $id);
        } else {
            Response::outputErrorMessage("Unable to delete campaign $id");
        }
    }
}
