<?php
/* +***********************************************************************************************************************************
 * The contents of this file are subject to the YetiForce Public License Version 1.1 (the "License"); you may not use this file except
 * in compliance with the License.
 * Software distributed under the License is distributed on an "AS IS" basis, WITHOUT WARRANTY OF ANY KIND, either express or implied.
 * See the License for the specific language governing rights and limitations under the License.
 * The Original Code is YetiForce.
 * The Initial Developer of the Original Code is YetiForce. Portions created by YetiForce are Copyright (C) www.yetiforce.com. 
 * All Rights Reserved.
 * *********************************************************************************************************************************** */

class Calendar_Calendar_Model extends Vtiger_Base_Model
{

	public $moduleName = 'Calendar';
	public $relationAcounts = [
		'Contacts' => ['vtiger_contactdetails', 'contactid', 'parentid'],
		'Project' => ['vtiger_project', 'projectid', 'linktoaccountscontacts'],
		'HelpDesk' => ['vtiger_troubletickets', 'ticketid', 'parent_id'],
		'ServiceContracts' => ['vtiger_servicecontracts', 'servicecontractsid', 'sc_related_to'],
	];

	public function getModuleName()
	{
		return $this->moduleName;
	}

	public function getQuery()
	{
		$query = (new \App\Db\Query())
			->select('vtiger_activity.*, relcrm.setype AS linkmod, procrm.setype AS processmod, subprocrm.setype AS subprocessmod')
			->from('vtiger_activity')
			->innerJoin('vtiger_crmentity', 'vtiger_activity.activityid = vtiger_crmentity.crmid')
			->innerJoin('vtiger_activitycf', 'vtiger_activity.activityid = vtiger_activitycf.activityid')
			->innerJoin('vtiger_crmentity relcrm', 'vtiger_activity.link = relcrm.crmid')
			->innerJoin('vtiger_crmentity procrm', 'vtiger_activity.process = procrm.crmid')
			->innerJoin('vtiger_crmentity subprocrm', 'vtiger_activity.subprocess = subprocrm.crmid')
			->where(['vtiger_activity.deleted' => 0]);
		\App\PrivilegeQuery::getConditions($query, $this->getModuleName());
		if ($this->get('start') && $this->get('end')) {
			$dbStartDateOject = DateTimeField::convertToDBTimeZone($this->get('start'));
			$dbStartDateTime = $dbStartDateOject->format('Y-m-d H:i:s');
			$dbStartDate = $dbStartDateOject->format('Y-m-d');
			$dbEndDateObject = DateTimeField::convertToDBTimeZone($this->get('end'));
			$dbEndDateTime = $dbEndDateObject->format('Y-m-d H:i:s');
			$dbEndDate = $dbEndDateObject->format('Y-m-d');
			$query->andWhere(['or',
					[
					'and',
						['>=', new \yii\db\Expression("CONCAT(date_start, ' ', time_start)"), $dbStartDateTime],
						['<=', new \yii\db\Expression("CONCAT(date_start, ' ', time_start)"), $dbEndDateTime]
				],
					[
					'and',
						['>=', new \yii\db\Expression("CONCAT(due_date, ' ', time_end)"), $dbStartDateTime],
						['<=', new \yii\db\Expression("CONCAT(date_start, ' ', time_start)"), $dbEndDateTime]
				],
					[
					'and',
						['<', 'date_start', $dbStartDate],
						['>', 'due_date', $dbEndDate]
				]
			]);
		}
		$types = $this->get('types');
		if (!empty($types)) {
			$query->andWhere(['vtiger_activity.activitytype' => $this->get('types')]);
		}
		switch ($this->get('time')) {
			case 'current':
				$query->andWhere(['vtiger_activity.status' => Calendar_Module_Model::getComponentActivityStateLabel('current')]);
				break;
			case 'history':
				$query->andWhere(['vtiger_activity.status' => Calendar_Module_Model::getComponentActivityStateLabel('history')]);
				break;
		}
		$activityStatus = $this->get('activitystatus');
		if (!empty($activityStatus)) {
			$query->andWhere(['vtiger_activity.status' => $activityStatus]);
		}
		$restrict = $this->get('restrict');
		if (!empty($restrict)) {
			$query->innerJoin('vtiger_users', 'vtiger_crmentity.smownerid = vtiger_users.id')
				->innerJoin('vtiger_groups', 'vtiger_crmentity.smownerid = vtiger_groups.groupid');

			$sql .= ' LEFT JOIN vtiger_users ON vtiger_crmentity.smownerid = vtiger_users.id '
				. 'LEFT JOIN vtiger_groups ON vtiger_crmentity.smownerid = vtiger_groups.groupid';
			$queryWhere .= $restrict;
		}
		if ($this->has('filters')) {
			foreach ($this->get('filters') as $filter) {
				$filterClassName = Vtiger_Loader::getComponentClassName('CalendarFilter', $filter['name'], 'Calendar');
				$filterInstance = new $filterClassName();
				if ($filterInstance->checkPermissions()) {
					$filterInstance->getCondition($query, $filter['value']);
				}
			}
		}
		$conditions = [];
		$currentUser = Users_Privileges_Model::getCurrentUserModel();
		$roleInstance = Settings_Roles_Record_Model::getInstanceById($currentUser->get('roleid'));
		$calendarAlloRecords = $roleInstance->get('clendarallorecords');
		if ($calendarAlloRecords === 1) {
			$subQuery = (new \App\Db\Query())->select('crmid')->from('u_#__crmentity_showners')->where(['userid' => $currentUser->getId()]);
			$conditions[] = ['vtiger_crmentity.crmid' => $subQuery];
		}
		$users = $this->get('user');
		if (!empty($users)) {
			$conditions[] = ['vtiger_crmentity.smownerid' => $users];
		}
		if (!$conditions) {
			$query->andWhere(array_merge(['or'], $conditions));
		}
		$query->orderBy('vtiger_activity.date_start,vtiger_activity.time_start');
		return $query;
	}

	public function getEntity()
	{
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$db = PearDatabase::getInstance();
		$dataReader = $this->getQuery()->createCommand()->query();
		$return = $records = $ids = [];
		while ($record = $dataReader->read()) {
			$records[] = $record;
			if (!empty($record['link'])) {
				$ids[] = $record['link'];
			}
			if (!empty($record['process'])) {
				$ids[] = $record['process'];
			}
			if (!empty($record['subprocess'])) {
				$ids[] = $record['subprocess'];
			}
		}
		$labels = \App\Record::getLabel($ids);

		foreach ($records as &$record) {
			$item = [];
			$crmid = $record['activityid'];
			$activitytype = $record['activitytype'];
			$item['id'] = $crmid;
			$item['module'] = $this->getModuleName();
			$item['title'] = $record['subject'];
			$item['url'] = 'index.php?module=' . $this->getModuleName() . '&view=Detail&record=' . $crmid;
			$item['set'] = $record['activitytype'] == 'Task' ? 'Task' : 'Event';
			$item['lok'] = $record['location'];
			$item['pri'] = $record['priority'];
			$item['sta'] = $record['status'];
			$item['vis'] = $record['visibility'];
			$item['state'] = $record['state'];
			$item['smownerid'] = vtlib\Functions::getOwnerRecordLabel($record['smownerid']);

			//translate
			$item['labels']['sta'] = vtranslate($record['status'], $this->getModuleName());
			$item['labels']['pri'] = vtranslate($record['priority'], $this->getModuleName());
			$item['labels']['state'] = vtranslate($record['state'], $this->getModuleName());

			//Relation
			$item['link'] = $record['link'];
			$item['linkl'] = $labels[$record['link']];
			$item['linkm'] = $record['linkmod'];
			//Process
			$item['process'] = $record['process'];
			$item['procl'] = vtlib\Functions::textLength($labels[$record['process']]);
			$item['procm'] = $record['processmod'];
			//Subprocess
			$item['subprocess'] = $record['subprocess'];
			$item['subprocl'] = vtlib\Functions::textLength($labels[$record['subprocess']]);
			$item['subprocm'] = $record['subprocessmod'];

			if ($record['linkmod'] != 'Accounts' && (!empty($record['link']) || !empty($record['process']))) {
				$findId = 0;
				$findMod = '';
				if (!empty($record['link'])) {
					$findId = $record['link'];
					$findMod = $record['linkmod'];
				}
				if (!empty($record['process'])) {
					$findId = $record['process'];
					$findMod = $record['processmod'];
				}
				$tabInfo = $this->relationAcounts[$findMod];
				if ($tabInfo) {
					$query = (new \App\Db\Query())
						->select('vtiger_account.accountid, vtiger_account.accountname')
						->from('vtiger_account')
						->innerJoin($tabInfo[0], "vtiger_account.accountid = {$tabInfo[0]}.{$tabInfo[2]}")
						->where([$tabInfo[1] => $findId]);
					$dataReader = $query->createCommand()->query();
					if ($dataReader->count()) {
						$row = $dataReader->read();
						$item['accid'] = $row['accountid'];
						$item['accname'] = $row['accountname'];
					}
				}
			}

			$dateTimeFieldInstance = new DateTimeField($record['date_start'] . ' ' . $record['time_start']);
			$userDateTimeString = $dateTimeFieldInstance->getFullcalenderDateTimevalue($currentUser);
			$startDateTimeDisplay = $dateTimeFieldInstance->getDisplayDateTimeValue();
			$startTimeDisplay = $dateTimeFieldInstance->getDisplayTime();
			$dateTimeComponents = explode(' ', $userDateTimeString);
			$dateComponent = $dateTimeComponents[0];
			$startTimeFormated = $dateTimeComponents[1];
			//Conveting the date format in to Y-m-d . since full calendar expects in the same format
			$startDateFormated = DateTimeField::__convertToDBFormat($dateComponent, $currentUser->get('date_format'));

			$dateTimeFieldInstance = new DateTimeField($record['due_date'] . ' ' . $record['time_end']);
			$userDateTimeString = $dateTimeFieldInstance->getFullcalenderDateTimevalue($currentUser);
			$endDateTimeDisplay = $dateTimeFieldInstance->getDisplayDateTimeValue();
			$dateTimeComponents = explode(' ', $userDateTimeString);
			$dateComponent = $dateTimeComponents[0];
			$endTimeFormated = $dateTimeComponents[1];
			//Conveting the date format in to Y-m-d . since full calendar expects in the same format
			$endDateFormated = DateTimeField::__convertToDBFormat($dateComponent, $currentUser->get('date_format'));

			$item['start'] = $startDateFormated . ' ' . $startTimeFormated;
			$item['end'] = $endDateFormated . ' ' . $endTimeFormated;

			// display date time values
			$item['start_display'] = $startDateTimeDisplay;
			$item['end_display'] = $endDateTimeDisplay;
			$item['hour_start'] = $startTimeDisplay;
			$hours = vtlib\Functions::getDateTimeHoursDiff($item['start'], $item['end']);
			$item['hours'] = vtlib\Functions::decimalTimeFormat($hours)['short'];
			$item['allDay'] = $record['allday'] == 1 ? true : false;
			$item['className'] = ' userCol_' . $record['smownerid'] . ' calCol_' . $activitytype;
			$return[] = $item;
		}
		return $return;
	}

	public function getEntityCount()
	{
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$db = PearDatabase::getInstance();
		$startDate = DateTimeField::convertToDBTimeZone($this->get('start'));
		$startDate = strtotime($startDate->format('Y-m-d H:i:s'));
		$endDate = DateTimeField::convertToDBTimeZone($this->get('end'));
		$endDate = strtotime($endDate->format('Y-m-d H:i:s'));

		if ($this->get('customFilter')) {
			$queryGenerator = new QueryGenerator($this->getModuleName());
			$queryGenerator->initForCustomViewById($this->get('customFilter'));
			$this->set('restrict', $queryGenerator->getWhereClause(true));
		}
		$dataReader = $this->getQuery()->createCommand()->query();
		$return = [];
		while ($record = $dataReader->read()) {
			$crmid = $record['activityid'];
			$activitytype = $record['activitytype'];

			$dateTimeFieldInstance = new DateTimeField($record['date_start'] . ' ' . $record['time_start']);
			$userDateTimeString = $dateTimeFieldInstance->getDisplayDateTimeValue($currentUser);
			$dateTimeComponents = explode(' ', $userDateTimeString);
			$dateComponent = $dateTimeComponents[0];
			$startDateFormated = DateTimeField::__convertToDBFormat($dateComponent, $currentUser->get('date_format'));

			$dateTimeFieldInstance = new DateTimeField($record['due_date'] . ' ' . $record['time_end']);
			$userDateTimeString = $dateTimeFieldInstance->getDisplayDateTimeValue($currentUser);
			$dateTimeComponents = explode(' ', $userDateTimeString);
			$dateComponent = $dateTimeComponents[0];
			$endDateFormated = DateTimeField::__convertToDBFormat($dateComponent, $currentUser->get('date_format'));

			$begin = new DateTime($startDateFormated);
			$end = new DateTime($endDateFormated);
			$end->modify('+1 day');
			$interval = DateInterval::createFromDateString('1 day');
			foreach (new DatePeriod($begin, $interval, $end) as $dt) {
				$date = strtotime($dt->format('Y-m-d'));
				if ($date >= $startDate && $date <= $endDate) {
					$date = date('Y-m-d', $date);

					$return[$date]['start'] = $date;
					$return[$date]['date'] = $date;
					$return[$date]['event'][$activitytype]['count'] += 1;
					$return[$date]['event'][$activitytype]['className'] = '  fc-draggable calCol_' . $activitytype;
					$return[$date]['event'][$activitytype]['label'] = vtranslate($activitytype, $this->getModuleName());
					$return[$date]['type'] = 'widget';
				}
			}
		}
		return array_values($return);
	}

	/**
	 * Static Function to get the instance of Vtiger Module Model for the given id or name
	 * @param mixed id or name of the module
	 */
	public static function getCleanInstance()
	{
		$instance = Vtiger_Cache::get('calendarModels', 'Calendar');
		if ($instance === false) {
			$instance = new self();
			Vtiger_Cache::set('calendarModels', 'Calendar', clone $instance);
			return $instance;
		} else {
			return clone $instance;
		}
	}

	public static function getCalendarTypes()
	{
		$calendarConfig = Array(
			'PLL_WORKING_TIME',
			'PLL_BREAK_TIME',
			'PLL_HOLIDAY'
		);
		return $calendarConfig;
	}
}
