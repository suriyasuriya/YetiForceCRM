{*<!--
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
* ("License"); You may not use this file except in compliance with the License
* The Original Code is:  vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
* Contributor(s): YetiForce.com
********************************************************************************/
-->*}
{strip}
	{if AppConfig::module('HelpDesk','CHECK_ACCOUNT_EXISTS') && $RECORD->get('parent_id') == 0}
		<div class="alert alert-danger marginBottom10px" role="alert">
			<strong>{\App\Language::translate('LBL_NO_ACCOUNTS_IN_HELPDESK',{$MODULE})}</strong>
			<span class="text-right">
				<a href="javascript:HelpDesk_Detail_Js.setAccountsReference();">
					<strong> [ <span class="fas fa-search" aria-hidden="true"></span> ]</strong>
				</a>
			</span>
		</div>
	{elseif AppConfig::module('HelpDesk','CHECK_SERVICE_CONTRACTS_EXISTS') && Vtiger_Module_Model::getInstance('ServiceContracts')->isActive() && $RECORD->get('servicecontractsid') == 0}
		{assign var=SERVICE_CONTRACTS value=$RECORD->getActiveServiceContracts()}
		<div class="alert {if $SERVICE_CONTRACTS}alert-warning{else}alert-danger{/if} selectServiceContracts marginBottom10px" role="alert">
			{if $SERVICE_CONTRACTS}
				<ul class="nav nav-pills float-right relative top10" role="tablist">
					{foreach item=ROW from=$SERVICE_CONTRACTS}
						<li role="presentation" class="btn btn-light popoverTooltip" data-id="{$ROW['servicecontractsid']}" title="{$ROW['subject']}" data-content="{\App\Language::translate('LBL_SET_SERVICE_CONTRACTS_REFERENCE_DESC',$MODULE)}">
							<span class="glyphicon glyphicon-link" aria-hidden="true"></span> {$ROW['subject']} {if $ROW['due_date']}({$ROW['due_date']}){/if}
						</li>
					{/foreach}
				</ul>
				<strong>{\App\Language::translate('LBL_NO_SERVICE_CONTRACTS_IN_HELPDESK',$MODULE)}</strong>
			{else}
				<strong>{\App\Language::translate('LBL_ACCOUNTS_NO_ACTIVE_SERVICE_CONTRACTS',$MODULE)}</strong>
			{/if}
		</div>
	{/if}
	<div class="col-md-12 paddingLRZero row">
		<div class="col-xs-12 col-sm-12 col-md-8">
			<div class="moduleIcon">
				<span class="detailViewIcon userIcon-{$MODULE}"></span>
			</div>
			<div class="paddingLeft5px detailViewHeaderFieldInformation">
				<h4 class="recordLabel margin0px textOverflowEllipsis" title="{$RECORD->getName()}">
					<span class="modCT_{$MODULE_NAME}">{$RECORD->getName()}</span>
					{assign var=RECORD_STATE value=\App\Record::getState($RECORD->getId())}
					{if $RECORD_STATE !== 'Active'}
						&nbsp;&nbsp;
						{assign var=COLOR value=AppConfig::search('LIST_ENTITY_STATE_COLOR')}
						<span class="label label-default" {if $COLOR[$RECORD_STATE]}style="background-color: {$COLOR[$RECORD_STATE]};"{/if}>
							{if \App\Record::getState($RECORD->getId()) === 'Trash'}
								{\App\Language::translate('LBL_ENTITY_STATE_TRASH')}
							{else}
								{\App\Language::translate('LBL_ENTITY_STATE_ARCHIVED')}
							{/if}
						</span>
					{/if}
				</h4>
				{assign var=RELATED_TO value=$RECORD->get('parent_id')}
				{if !empty($RELATED_TO)}
					<div class="paddingLeft5px">
						<span class="muted"></span>
						<h5 class="margin0px"><span class="">{$RECORD->getDisplayValue('parent_id')}</span></h5>
					</div>
				{/if}
				{assign var=PRIORITY value=$RECORD->get('ticketpriorities')}
				{if !empty($PRIORITY)}
					<div class="paddingLeft5px">
						<span class="muted">{\App\Language::translate('Priority',$MODULE_NAME)} - </span>
						{$RECORD->getDisplayValue('ticketpriorities')}
					</div>
				{/if}
				{assign var=STATUS value=$RECORD->get('ticketstatus')}
				{if !empty($STATUS)}
					<div class="paddingLeft5px">
						<span class="muted">{\App\Language::translate('Status',$MODULE_NAME)}: </span>
						{$RECORD->getDisplayValue('ticketstatus')}
					</div>
				{/if}
				<div class="muted paddingLeft5px">
					{\App\Language::translate('Assigned To',$MODULE_NAME)}: {$RECORD->getDisplayValue('assigned_user_id')}
				</div>
				{assign var=SHOWNERS value=$RECORD->getDisplayValue('shownerid')}
				{if $SHOWNERS != ''}
					<div class="muted paddingLeft5px">
						{\App\Language::translate('Share with users',$MODULE_NAME)}: {$SHOWNERS}
					</div>
				{/if}
			</div>
		</div>
		{include file=\App\Layout::getTemplatePath('DetailViewHeaderFields.tpl', $MODULE_NAME)}
	</div>
{/strip}
