/* {[The file is published on the basis of YetiForce Public License 3.0 that can be found in the following directory: licenses/LicenseEN.txt or yetiforce.com]} */
'use strict';
jQuery.Class("Calendar_QuickCreate_Js", {}, {
	container: false,
	getContainer() {
		return this.container;
	},
	setContainer: function (container) {
		this.container = container;
	},
	registerExtendCalendar: function () {
		let container = this.getContainer();
		let instance = Calendar_Calendar_Js.getInstanceByView('CalendarExtended');
		instance.calendarView = this.getContainer().find('.js-calendar__container');
		instance.setContainer(this.getContainer());

		var selectDays = function (startDate, endDate) {
			let start_hour = $('#start_hour').val(),
				end_hour = $('#end_hour').val(),
				view = instance.getCalendarView().fullCalendar('getView');
			if (endDate.hasTime() == false) {
				endDate.add(-1, 'days');
			}
			startDate = startDate.format();
			endDate = endDate.format();
			if (start_hour == '') {
				start_hour = '00';
			}
			if (end_hour == '') {
				end_hour = '00';
			}
			if (view.name != 'agendaDay' && view.name != 'agendaWeek') {
				startDate = startDate + 'T' + start_hour + ':00';
				endDate = endDate + 'T' + end_hour + ':00';
				if (startDate == endDate) {
					let defaulDuration = 0;
					if (container.find('[name="activitytype"]').val() == 'Call') {
						defaulDuration = container.find('[name="defaultCallDuration"]').val();
					} else {
						defaulDuration = container.find('[name="defaultOtherEventDuration"]').val();
					}
					endDate = moment(endDate).add(defaulDuration, 'minutes').toISOString();
				}
			}
			let dateFormat = container.find('[name="date_start"]').data('dateFormat').toUpperCase(),
				timeFormat = container.find('[name="time_start"]').data('format'),
				defaultTimeFormat = '';
			if (timeFormat == 24) {
				defaultTimeFormat = 'HH:mm';
			} else {
				defaultTimeFormat = 'hh:mm A';
			}
			container.find('[name="date_start"]').val(moment(startDate).format(dateFormat));
			container.find('[name="due_date"]').val(moment(endDate).format(dateFormat));
			container.find('[name="time_start"]').val(moment(startDate).format(defaultTimeFormat));
			container.find('[name="time_end"]').val(moment(endDate).format(defaultTimeFormat));
		};
		FC.views.year = FC.views.year.extend({
			selectDays: selectDays
		});
		instance.renderCalendar(true);
		instance.registerChangeView();
		instance.loadCalendarData();
		instance.selectDays = selectDays;
	},
	registerStandardCalendar: function () {
		const thisInstance = this;
		let container = this.getContainer();
		let data = container.find('form')
		let user = data.find('[name="assigned_user_id"]');
		let dateStartEl = data.find('[name="date_start"]');
		let dateEnd = data.find('[name="due_date"]');
		user.on('change', function (e) {
			var element = $(e.currentTarget);
			var data = element.closest('form');
			thisInstance.getNearCalendarEvent(data);
		});
		dateStartEl.on('change', function (e) {
			var element = $(e.currentTarget);
			var data = element.closest('form');
			thisInstance.getNearCalendarEvent(data);
		});
		data.find('ul li a').on('click', function (e) {
			var element = $(e.currentTarget);
			var data = element.closest('form');
			data.find('.addedNearCalendarEvent').remove();
			thisInstance.getNearCalendarEvent(data);
		});
		data.on('click', '.nextDayBtn', function () {
			var dateStartEl = data.find('[name="date_start"]')
			var startDay = dateStartEl.val();
			var dateStartFormat = dateStartEl.data('date-format');
			startDay = moment(Vtiger_Helper_Js.convertToDateString(startDay, dateStartFormat, '+7', ' ')).format(dateStartFormat.toUpperCase());
			dateStartEl.val(startDay);
			dateEnd.val(startDay);
			thisInstance.getNearCalendarEvent(data);
		});
		data.on('click', '.previousDayBtn', function () {
			var dateStartEl = data.find('[name="date_start"]')
			var startDay = dateStartEl.val();
			var dateStartFormat = dateStartEl.data('date-format');
			startDay = moment(Vtiger_Helper_Js.convertToDateString(startDay, dateStartFormat, '-7', ' ')).format(dateStartFormat.toUpperCase());
			dateStartEl.val(startDay);
			dateEnd.val(startDay);
			thisInstance.getNearCalendarEvent(data);
		});
		data.on('click', '.dateBtn', function (e) {
			var element = $(e.currentTarget);
			dateStartEl.val(element.data('date'));
			data.find('[name="due_date"]').val(element.data('date'));
			data.find('[name="date_start"]').trigger('change');
		});
		thisInstance.getNearCalendarEvent(data);
	},
	getNearCalendarEvent: function (container) {
		var thisInstance = this;
		var dateStartVal = container.find('[name="date_start"]').val();
		if (typeof dateStartVal === "undefined" || dateStartVal === '') {
			return;
		}
		var params = {
			module: 'Calendar',
			view: 'QuickCreateEvents',
			currentDate: dateStartVal,
			user: container.find('[name="assigned_user_id"]').val(),
		}
		var progressIndicatorElement = $.progressIndicator({
			position: 'html',
			blockInfo: {
				enabled: true,
				elementToBlock: container.find('.eventsTable')
			}
		});
		AppConnector.request(params).done(function (events) {
			progressIndicatorElement.progressIndicator({'mode': 'hide'});
			container.find('.eventsTable').remove();
			container.append(events);
			Vtiger_Header_Js.getInstance().registerHelpInfo(container);
		});
	},
	registerEvents: function (container) {
		let calendarType = container.closest('.js-modal-container').find('.js-calendar-type').val();
		this.setContainer(container);
		if (calendarType === 'Extended') {
			this.registerExtendCalendar();
		} else {
			this.registerStandardCalendar();
		}
	}
});

