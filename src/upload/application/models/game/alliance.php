<?php
/**
 * Alliance Model
 *
 * PHP Version 5.5+
 *
 * @category Model
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.4
 */
namespace application\models\game;

/**
 * Alliance Class
 *
 * @category Classes
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.1.0
 */
class Alliance
{

    private $db = null;

    /**
     * __construct()
     */
    public function __construct($db)
    {
        // use this to make queries
        $this->db = $db;
    }

    /**
     * __destruct
     * 
     * @return void
     */
    public function __destruct()
    {
        $this->db->closeConnection();
    }
    
    /**
     * Get Alliance Data By ID
     * 
     * @param int $alliance_id Alliance ID
     * 
     * @return array
     */
    public function getAllianceDataById($alliance_id)
    {
        return $this->db->queryFetch(
            "SELECT a.`alliance_id`,
                    a.`alliance_image`,
                    a.`alliance_name`,
                    a.`alliance_tag`,
                    a.`alliance_description`,
                    a.`alliance_web`,
                    a.`alliance_request_notallow`,
                    (SELECT COUNT(user_id) AS `ally_members` 
                        FROM `" . USERS . "` 
                        WHERE `user_ally_id` = a.`alliance_id`) AS `ally_members`
            FROM `" . ALLIANCE . "` AS a
            WHERE a.`alliance_id` = '" . (int)$alliance_id . "'
            LIMIT 1;"
        );
    }
    
    /**
     * Create a new alliance with the provided params
     * 
     * @param string $alliance_name Alliance Name
     * @param string $alliance_tag  Alliance Tag
     * @param int $user_id          User ID
     * @param string $founder_rank  Founder Rank
     * 
     * @return void
     */
    public function createNewAlliance($alliance_name, $alliance_tag, $user_id, $founder_rank)
    {
        $this->db->query(
            "INSERT INTO " . ALLIANCE . " SET
            `alliance_name`='" . $alliance_name . "',
            `alliance_tag`='" . $alliance_tag . "' ,
            `alliance_owner`='" . (int)$user_id . "',
            `alliance_owner_range` = '" . $founder_rank . "',
            `alliance_register_time`='" . time() . "'"
        );

        $new_ally_id = $this->db->insertId();

        $this->db->query(
            "INSERT INTO " . ALLIANCE_STATISTICS . " SET
            `alliance_statistic_alliance_id`='" . $new_ally_id . "'"
        );

        $this->db->query("UPDATE " . USERS . " SET
            `user_ally_id`='" . $new_ally_id . "',
            `user_ally_register_time`='" . time() . "'
            WHERE `user_id`='" . (int)$user_id . "'"
        );
    }
    
    /**
     * Search an alliance by name or tag
     * 
     * @param string $name_tag Name or Tag
     * 
     * @return array
     */
    public function searchAllianceByNameTag($name_tag)
    {
        return $this->db->query(
            "SELECT a.*,
                (SELECT COUNT(user_id) AS `ally_members` 
                    FROM `" . USERS . "` 
                    WHERE `user_ally_id` = a.`alliance_id`) AS `ally_members`
            FROM " . ALLIANCE . " AS a
            WHERE a.alliance_name LIKE '%" . $this->db->escapeValue($name_tag) . "%' OR
                    a.alliance_tag LIKE '%" . $this->db->escapeValue($name_tag) . "%' LIMIT 30"
        );
    }
    
    /**
     * Update users table to set the alliance request
     * 
     * @param int    $alliance_id  Alliance ID
     * @param string $text Request Text
     * @param int    $user_id      User ID
     * 
     * @retun void
     */
    public function createNewUserRequest($alliance_id, $text, $user_id)
    {
        $this->db->query(
            "UPDATE " . USERS . " SET
            `user_ally_request` = '" . (int)$alliance_id . "' ,
            `user_ally_request_text` = '" . $text . "',
            `user_ally_register_time` = '" . time() . "'
            WHERE `user_id`='" . (int)$user_id . "'"
        );
    }
    
    /**
     * Cancel user request
     * 
     * @param int $user_id User ID
     * 
     * @retun void
     */
    public function cancelUserRequestById($user_id)
    {
        $this->db->query(
            "UPDATE " . USERS . "
                SET `user_ally_request` = '0'
            WHERE `user_id`= '" . (int)$user_id . "'"
        );
    }
    
    /**
     * Exit alliance
     * 
     * @param int $user_id User ID
     * 
     * @retun void
     */
    public function exitAlliance($user_id)
    {
        $this->db->query(
            "UPDATE `" . USERS . "` SET
            `user_ally_id` = '0',
            `user_ally_rank_id` = '0'
            WHERE `user_id`='" . (int)$user_id . "'"
        );
    }
    
    /**
     * 
     * @param type $alliance_id
     * @return type
     */
    public function getAllianceRequestsCount($alliance_id)
    {
        return $this->db->queryFetch(
            "SELECT COUNT(user_id) AS total_requests
                FROM `" . USERS . "`
                WHERE `user_ally_request` = '" . (int)$alliance_id . "'"
        );
    }
    
    /**
     * Get alliance members
     * 
     * @param type $user_alliance_id
     * 
     * @return type
     */
    public function getAllianceMembers($alliance_id, $sort1, $sort2)
    {
        if ($sort2) {

            $sort = $this->returnSort($sort1, $sort2);
        } else {

            $sort = '';
        }
        
        return $this->db->query(
            "SELECT u.user_id, 
                    u.user_onlinetime, 
                    u.user_name, 
                    u.user_galaxy, 
                    u.user_system, 
                    u.user_planet, 
                    u.user_ally_register_time, 
                    u.user_ally_rank_id,
                    s.user_statistic_total_points
            FROM `" . USERS . "` AS u
            INNER JOIN `" . USERS_STATISTICS . "`AS s ON u.user_id = s.user_statistic_user_id
            WHERE u.user_ally_id='" . (int)$alliance_id . "'" . $sort
        );
    }
    
    /**
     * Get alliance members filtered by alliance ID
     * 
     * @param int $alliance_id Alliance ID
     * 
     * @return array
     */
    public function getAllianceMembersById($alliance_id)
    {
        return $this->db->query(
            "SELECT `user_id`, `user_name`
                FROM `" . USERS . "`
                WHERE `user_ally_id` = '" . (int)$alliance_id . "'"
        );
    }
    
    /**
     * Get alliance members filtered by alliance ID and Rank ID
     * 
     * @param int $alliance_id Alliance ID
     * @param int $rank_id     Rank ID
     * 
     * @return array
     */
    public function getAllianceMembersByIdAndRankId($alliance_id, $rank_id)
    {
        return $this->db->query(
            "SELECT `user_id`, `user_name`
            FROM `" . USERS . "`
            WHERE `user_ally_id` = '" . (int)$alliance_id . "' AND
                `user_ally_rank_id` = '" . (int)$rank_id . "'"
        );
    }
    
    /**
     * Update alliance ranks
     * 
     * @param int    $alliance_id    Alliance ID
     * @param array  $alliance_ranks Alliance Ranks
     * @param string $rank_name      Rank Name
     * 
     * @return void
     */
    public function createNewAllianceRank($alliance_id, $alliance_ranks, $rank_name)
    {
        $name   = $this->db->escapeValue(strip_tags($rank_name));

        $alliance_ranks[] = [
            'name' => $name,
            'mails' => 0,
            'delete' => 0,
            'kick' => 0,
            'bewerbungen' => 0,
            'administrieren' => 0,
            'bewerbungenbearbeiten' => 0,
            'memberlist' => 0,
            'onlinestatus' => 0,
            'rechtehand' => 0
        ];

        $ranks = serialize($alliance_ranks);
        
        $this->updateAllianceRanks($alliance_id, $ranks);
    }
    
    /**
     * Update alliance ranks
     * 
     * @param int    $alliance_id Alliance ID
     * @param string $ranks       Ranks
     */
    public function updateAllianceRanks($alliance_id, $ranks)
    {
        $this->db->query(
            "UPDATE " . ALLIANCE . " SET
                `alliance_ranks`='" . $ranks . "'
            WHERE `alliance_id` = '" . (int)$alliance_id . "'"
        );
    }
}

/* end of buildings.php */