<?php

/**
 * SchedConfDAO.inc.php
 *
 * Copyright (c) 2003-2007 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package schedConf
 *
 * Class for Scheduled Conference DAO.
 * Operations for retrieving and modifying SchedConf objects.
 *
 * $Id$
 */

import ('schedConf.SchedConf');

class SchedConfDAO extends DAO {

	/**
	 * Constructor.
	 */
	function SchedConfDAO() {
		parent::DAO();
	}
	
	/**
	 * Retrieve a scheduled conference by ID.
	 * @param $schedConfId int
	 * @return SchedConf
	 */
	function &getSchedConf($schedConfId) {
		$result = &$this->retrieve(
			'SELECT * FROM sched_confs WHERE sched_conf_id = ?', $schedConfId
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = &$this->_returnSchedConfFromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		unset($result);
		return $returner;
	}
	
	/**
	 * Retrieve a scheduled conference by path.
	 * @param $path string
	 * @return SchedConf
	 */
	function &getSchedConfByPath($path, $conferenceId = null) {
		if($conferenceId == null) {
			$conference = &Request::getConference();

			if(!$conference)
				$conferenceId = -1;
			else
				$conferenceId = $conference->getConferenceId();
		}
		
		$returner = null;
		$result = &$this->retrieve(
			'SELECT * FROM sched_confs WHERE path = ? and conference_id = ?',
			array($path, $conferenceId));
		
		if ($result->RecordCount() != 0) {
			$returner = &$this->_returnSchedConfFromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		unset($result);
		return $returner;
	}
	
	/**
	 * Internal function to return a scheduled conference object from a row.
	 * @param $row array
	 * @return SchedConf
	 */
	function &_returnSchedConfFromRow(&$row) {
		$schedConf = &new SchedConf();
		$schedConf->setSchedConfId($row['sched_conf_id']);
		$schedConf->setTitle($row['title']);
		$schedConf->setPath($row['path']);
		$schedConf->setSequence($row['seq']);
		$schedConf->setEnabled($row['enabled']);
		$schedConf->setConferenceId($row['conference_id']);
		$schedConf->setStartDate($this->datetimeFromDB($row['start_date']));
		$schedConf->setEndDate($this->datetimeFromDB($row['end_date']));
		
		HookRegistry::call('SchedConfDAO::_returnSchedConfFromRow', array(&$schedConf, &$row));

		return $schedConf;
	}

	/**
	 * Insert a new scheduled conference.
	 * @param $schedConf SchedConf
	 */	
	function insertSchedConf(&$schedConf) {
		$this->update(
			sprintf('INSERT INTO sched_confs
				(conference_id, title, path, seq, enabled, start_date, end_date)
				VALUES
				(?, ?, ?, ?, ?, %s, %s)',
				$this->datetimeToDB($schedConf->getStartDate()),
				$this->datetimeToDB($schedConf->getEndDate())),
			array(
				$schedConf->getConferenceId(),
				$schedConf->getTitle(),
				$schedConf->getPath(),
				$schedConf->getSequence() == null ? 0 : $schedConf->getSequence(),
				$schedConf->getEnabled() ? 1 : 0
			)
		);
		
		$schedConf->setSchedConfId($this->getInsertSchedConfId());
		return $schedConf->getSchedConfId();
	}
	
	/**
	 * Update an existing scheduled conference.
	 * @param $schedConf SchedConf
	 */
	function updateSchedConf(&$schedConf) {
		return $this->update(
			sprintf('UPDATE sched_confs
				SET
					conference_id = ?,
					title = ?,
					path = ?,
					seq = ?,
					enabled = ?,
					start_date = %s,
					end_date = %s
				WHERE sched_conf_id = ?',
				$this->datetimeToDB($schedConf->getStartDate()),
				$this->datetimeToDB($schedConf->getEndDate())),
			array(
				$schedConf->getConferenceId(),
				$schedConf->getTitle(),
				$schedConf->getPath(),
				$schedConf->getSequence(),
				$schedConf->getEnabled() ? 1 : 0,
				$schedConf->getSchedConfId()
			)
		);
	}
	
	/**
	 * Delete a scheduled conference, INCLUDING ALL DEPENDENT ITEMS.
	 * @param $schedConf SchedConf
	 */
	function deleteSchedConf(&$schedConf) {
		return $this->deleteSchedConfById($schedConf->getSchedConfId());
	}
	
	/**
	 * Retrieves all scheduled conferences for a conference
	 * @param $conferenceId
	 */
	function &getSchedConfsByConferenceId($conferenceId) {
		$result = &$this->retrieve(
			'SELECT i.*
			FROM sched_confs i
				WHERE i.conference_id = ?',
			$conferenceId
		);
		
		$returner = &new DAOResultFactory($result, $this, '_returnSchedConfFromRow');
		return $returner;
	}

	/**
	 * Delete all scheduled conferences by conference ID.
	 * @param $schedConfId int
	 */
	function deleteSchedConfsByConferenceId($conferenceId) {
		$schedConfs = $this->getSchedConfsByConferenceId($conferenceId);
		
		while (!$schedConfs->eof()) {
			$schedConf = &$schedConfs->next();
			$this->deleteSchedConfById($schedConf->getSchedConfId());
		}
	}
	
	/**
	 * Delete a scheduled conference by ID, INCLUDING ALL DEPENDENT ITEMS.
	 * @param $schedConfId int
	 */
	function deleteSchedConfById($schedConfId) {
		$schedConfSettingsDao = &DAORegistry::getDAO('SchedConfSettingsDAO');
		$schedConfSettingsDao->deleteSettingsBySchedConf($schedConfId);

		$trackDao = &DAORegistry::getDAO('TrackDAO');
		$trackDao->deleteTracksBySchedConf($schedConfId);

		$notificationStatusDao = &DAORegistry::getDAO('NotificationStatusDAO');
		$notificationStatusDao->deleteNotificationStatusBySchedConf($schedConfId);

		$emailTemplateDao = &DAORegistry::getDAO('EmailTemplateDAO');
		$emailTemplateDao->deleteEmailTemplatesBySchedConf($schedConfId);

		$registrationDao = &DAORegistry::getDAO('RegistrationDAO');
		$registrationDao->deleteRegistrationsBySchedConf($schedConfId);

		$paperDao = &DAORegistry::getDAO('PaperDAO');
		$paperDao->deletePapersBySchedConfId($schedConfId);

		$roleDao = &DAORegistry::getDAO('RoleDAO');
		$roleDao->deleteRoleBySchedConfId($schedConfId);

		$groupDao = &DAORegistry::getDAO('GroupDAO');
		$groupDao->deleteGroupsBySchedConfId($schedConfId);

		$announcementDao = &DAORegistry::getDAO('AnnouncementDAO');
		$announcementDao->deleteAnnouncementsBySchedConf($schedConfId);

		return $this->update(
			'DELETE FROM sched_confs WHERE sched_conf_id = ?', $schedConfId
		);
	}
	
	/**
	 * Retrieve all scheduled conferences.
	 * @return DAOResultFactory containing matching scheduled conferences
	 */
	function &getSchedConfs($rangeInfo = null) {
		$result = &$this->retrieveRange(
			'SELECT * FROM sched_confs ORDER BY seq',
			false, $rangeInfo
		);

		$returner = &new DAOResultFactory($result, $this, '_returnSchedConfFromRow');
		return $returner;
	}
	
	/**
	 * Retrieve all enabled scheduled conferences
	 * @param conferenceId optional conference ID
	 * @return array SchedConfs ordered by sequence
	 */
	 function &getEnabledSchedConfs($conferenceId = null) 
	 {
		$result = &$this->retrieve('
			SELECT i.* FROM sched_confs i
				LEFT JOIN conferences c ON (i.conference_id = c.conference_id)
			WHERE i.enabled=1
				AND c.enabled = 1'
				. ($conferenceId?' AND i.conference_id = ?':'')
			. ' ORDER BY c.seq, i.seq',
			$conferenceId===null?-1:$conferenceId);
		
		$resultFactory = &new DAOResultFactory($result, $this, '_returnSchedConfFromRow');
		return $resultFactory;
	}
	
	/**
	 * Retrieve the IDs and titles of all scheduled conferences in an associative array.
	 * @return array
	 */
	function &getSchedConfTitles() {
		$schedConfs = array();
		
		$result = &$this->retrieve(
			'SELECT sched_conf_id, title FROM sched_confs ORDER BY seq'
		);
		
		while (!$result->EOF) {
			$schedConfId = $result->fields[0];
			$sched_confs[$schedConfId] = $result->fields[1];
			$result->moveNext();
		}
		$result->Close();
		unset($result);
	
		return $sched_confs;
	}
	
	/**
	* Retrieve enabled scheduled conference IDs and titles in an associative array
	* @return array
	*/
	function &getEnabledSchedConfTitles() {
		$schedConfs = array();
		
		$result = &$this->retrieve('
			SELECT i.sched_conf_id, i.title FROM schedConfs i
				LEFT JOIN conferences c ON (i.conference_id = c.conference_id)
			WHERE i.enabled=1
				AND c.enabled = 1
			ORDER BY seq'
		);
		
		while (!$result->EOF) {
			$schedConfId = $result->fields[0];
			$schedConfs[$schedConfId] = $result->fields[1];
			$result->moveNext();
		}
		$result->Close();
		unset($result);
	
		return $schedConfs;
	}
	
	/**
	 * Check if a scheduled conference exists with a specified path.
	 * @param $path the path of the scheduled conference
	 * @return boolean
	 */
	function schedConfExistsByPath($path) {
		$result = &$this->retrieve(
			'SELECT COUNT(*) FROM sched_confs WHERE path = ?', $path
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;

		$result->Close();
		unset($result);

		return $returner;
	}
	
	/**
	 * Sequentially renumber scheduled conferences in their sequence order.
	 */
	function resequenceSchedConfs() {
		$result = &$this->retrieve(
			'SELECT sched_conf_id FROM sched_confs ORDER BY seq'
		);
		
		for ($i=1; !$result->EOF; $i++) {
			list($schedConfId) = $result->fields;
			$this->update(
				'UPDATE sched_confs SET seq = ? WHERE sched_conf_id = ?',
				array(
					$i,
					$schedConfId
				)
			);
			
			$result->moveNext();
		}

		$result->close();
		unset($result);
	}
	
	/**
	 * Get the ID of the last inserted scheduled conference.
	 * @return int
	 */
	function getInsertSchedConfId() {
		return $this->getInsertId('sched_confs', 'sched_conf_id');
	}
	
	/**
	 * Retrieve most recent enabled scheduled conference of a given conference
	 * @return array SchedConfs ordered by sequence
	 */
	 function &getCurrentSchedConfs($conferenceId) 
	 {
		$result = &$this->retrieve('
			SELECT i.* FROM sched_confs i
				LEFT JOIN conferences c ON (i.conference_id = c.conference_id)
			WHERE i.enabled=1
				AND c.enabled = 1
				AND i.conference_id = ?
				AND i.start_date < NOW()
				AND i.end_date > NOW()
			ORDER BY c.seq, i.seq',
			$conferenceId);
		
		$resultFactory = &new DAOResultFactory($result, $this, '_returnSchedConfFromRow');
		return $resultFactory;
	}

	/**
	 * Check if one or more archived scheduled conferences exist for a conference.
	 * @param $conferenceId the conference owning the scheduled conference
	 * @return boolean
	 */
	function archivedSchedConfsExist($conferenceId) {
		$result = &$this->retrieve(
			'SELECT COUNT(*) FROM sched_confs WHERE conference_id = ? AND end_date < now()', $conferenceId
		);
		$returner = isset($result->fields[0]) && $result->fields[0] >= 1 ? true : false;

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Check if one or more archived scheduled conferences exist for a conference.
	 * @param $conferenceId the conference owning the scheduled conference
	 * @return boolean
	 */
	function currentSchedConfsExist($conferenceId) {
		$result = &$this->retrieve(
			'SELECT COUNT(*) FROM sched_confs WHERE conference_id = ? AND start_date < now() AND end_date > now()', $conferenceId
		);
		$returner = isset($result->fields[0]) && $result->fields[0] >= 1 ? true : false;

		$result->Close();
		unset($result);

		return $returner;
	}
}

?>