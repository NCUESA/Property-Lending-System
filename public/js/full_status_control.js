class Area {
	constructor(area_components) {
		this.components = area_components;
	}

	show(disable = false) {
		$(this.components).each(function (index, component) {
			$(component).prop('hidden', false);
			if (!disable) {
				$(component).prop('disabled', false);
			}
		});
	}

	hide() {
		$(this.components).each(function (index, component) {
			$(component).prop('hidden', true);
			$(component).prop('disabled', true);
		});
	}

	disable() {
		$(this.components).each(function (index, component) {
			$(component).prop('disabled', true);
		});
	}
	enable() {
		$(this.components).each(function (index, component) {
			$(component).prop('disabled', false);
		});
	}
	clearText() {
		$(this.components).each(function (index, component) {
			$(component).text('');
		})
	}
	clearValidation() {
		$(this.components).each(function (index, component) {
			$(component).removeClass('is-valid');
			$(component).removeClass('is-invalid');
		});
	}
}

class LendItem {
	static STATUS_VALUE = Object.freeze({
		BACK_TO_SYS: 0,
		LENDING_OUT: 1,
		WAIT_FOR_LEND: 2,
		RETURNED: 3
	});
	constructor(id, status) {
		this.id = id;
		this.status = status;
		this.checked = false;
		this.$cardObj = new Object;
	}
	bindDOM() {
		let concat_str = '#item-' + this.id;
		this.$cardObj = $(concat_str);
	}
	check() {
		this.checked = true;
		this.$cardObj.addClass('alert-success');
	}
	uncheck() {
		this.checked = false;
		this.$cardObj.removeClass('alert-success');
	}
}

// 全域變數 Global Object
let G_itemList = new Map();
const CODE_2_STATUS = {
	0: "comment",          // 系統拒絕
	1: "return",  // 歸還
	2: "borrow",    // 借出
	3: "comment"    // 系統歸還
};
let G_manuplateStatus = 'none';

$(function () {
	// 初始化
	const urlParams = new URLSearchParams(window.location.search);
	const URL_ID = urlParams.get('id');
	const loadingModal = new bootstrap.Modal(document.getElementById('loading-modal'), {
		keyboard: false
	})

	let completed = 0;
	const total_task = 7;

	function taskDone(msg) {
		completed++;
		//completed = Math.min(completed, total_task);
		let percent = Math.round((completed / total_task) * 100);
		$("#loading-progress-bar")
			.css("width", percent + "%")
			.attr("aria-valuenow", percent)
			.text(percent + "%");



		if (completed === total_task) {
			$("#loading-modal-title").text("讀取完畢 Good To Go");
			setTimeout(() => loadingModal.hide(), 1000);
		}
		else {
			$("#loading-modal-title").text(msg || "讀取中，請稍後...");
			setTimeout(2000);
		}
	}

	if (URL_ID) {
		loadingModal.show();
		// 借用人
		loadBorrowPerson(URL_ID);
		taskDone('讀');

		// 狀態
		loadStatusBar(URL_ID);
		taskDone('取');

		// 借用物品
		loadBorrowItems(URL_ID, function (lending_property) {
			taskDone('中');
			showBorrowItems(lending_property);
			taskDone('請');
			loadManuplationData(URL_ID);
			taskDone('稍');
		});

		// 承辦人
		loadChargePersonList();
		taskDone('候');
		// 控制區
		manuplateControlArea("disabled");
		taskDone(':)');
	}

	$('#scan_list').on('input', function () {
		const inputVal = $(this).val();
		const item = G_itemList.get(inputVal);	// G_itemList is Map
		if (!item) {
			$('#scan_list').removeClass('is-valid').addClass('is-invalid');
			$('#scan-check').removeClass('valid-feedback').addClass('invalid-feedback');
			$('#scan-check').text('清單無此物品');
			$('#scan_list').val('');
		}
		else if (inputVal.length >= 8) {
			if (item.status === 0) // 0: 退回系統
			{
				$('#scan_list').removeClass('is-valid').addClass('is-invalid');
				$('#scan-check').removeClass('valid-feedback').addClass('invalid-feedback');
				$('#scan-check').text('此物已退回系統');
				$('#scan_list').val('');
			}
			else if (item.checked == true) {
				$('#scan_list').removeClass('is-valid').addClass('is-invalid');
				$('#scan-check').removeClass('valid-feedback').addClass('invalid-feedback');
				$('#scan-check').text('掃過了啦');
				$('#scan_list').val('');
			}
			else {
				$('#scan_list').removeClass('is-invalid').addClass('is-valid');
				$('#scan-check').removeClass('invalid-feedback').addClass('valid-feedback');
				$('#scan-check').text('掃描成功');
				item.check();
				$('#scan_list').val('');
			}
		}
	});

	$('#start-lending').on('click', () => {
		loadStatus(URL_ID, function (status) {
			const code2status = {
				0: "comment",          // 系統拒絕
				1: "return",  // 歸還
				2: "borrow",    // 借出
				3: "comment"    // 系統歸還
			};
			manuplateControlArea(code2status[status]);
		});
	});

	$('#quit-without-save').on('click', () => {
		manuplateControlArea('disabled');
	});


	let readyToSubmit = false;
	$('#save-data').on('click', function (e) {
		console.log(readyToSubmit);
		if (readyToSubmit) {
			// 第二次點擊 -> 真的送資料
			$('#wait_message_window').show();
			saveDataToBackend();
		}
		else {
			if (!validateForm(G_manuplateStatus)) {
				$('#error_message_window').show();
				e.stopPropagation();
				return false;
			}
			else {
				$('#error_message_window').hide();
				$('#info_message_window').show();
				readyToSubmit = true;
			}
		}
	});
});

function saveDataToBackend() {
	let borrow = [];
	let no_borrow = [];

	G_itemList.forEach((item, value) => {
		if (item.checked == true) {
			borrow.push(item.id);
		}
		else {
			no_borrow.push(item.id);
		}
	});
	console.log(borrow);
	console.log(no_borrow);

	let pack_data = {
		sa_manuplate: G_manuplateStatus,
		sa_lending_person_name: $('#sa_lending_person_name').val(),
		sa_lending_date: $('#sa_lending_date').val(),
		sa_deposit_take: $('input[name="sa_deposit_take"]:checked').val(),
		sa_id_take: $('input[name="sa_id_take"]:checked').val(),
		sa_id_deposit_box_number: $('#sa_id_deposit_box_number').val(),

		sa_return_person_name: $('#sa_return_person_name').val(),
		sa_returned_date: $('#sa_returned_date').val(),
		sa_deposit_returned: $('input[name="sa_deposit_returned"]:checked').val(),
		sa_id_returned: $('input[name="sa_id_returned"]:checked').val(),

		sa_remark: $('#sa_remark').val(),

		borrow_id: $('#borrow_id').val(),
		borrow_items: borrow,
		no_borrow_items: no_borrow
	};
	console.log(pack_data);
	// Ajax Send to backend

	$.ajax({
		type: 'POST',
		url: '/borrow/item/final',
		data: {
			pack_data: JSON.stringify(pack_data),
			_token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
		},
		success: function (response) {
			//console.log(response);
			if (response.success) {
				$('#done_message_window').show();
				$('#done_message').text(response.message);
				location.reload();
			}
			else {
				$('#error_message_window').show();
				$('#error_message').text(response.message);
			}
		},
		error: function (error) {
			$('#error_message_window').show();
			$('#error_message').text(error);
		}
	});

}

function validateForm(status) {
	let valid = true;
	let $errorWindow = $('#error_message');

	$errorWindow.text();

	const errorMessages = {
		borrow__sa_lending_person_required: '借出人員未選取',
		borrow__sa_lending_date_required: '借出日期未填寫',
		borrow__deposit_box_required: '押金盒號碼未填寫',
		return__sa_return_person_name_required: '歸還人員未選取',
		return__sa_returned_date_required: '歸還日期未填寫',
		return__item_empty: '未歸還任何物品'
	};

	let errors = [];

	if (status === 'borrow') {
		if (!$('#sa_lending_person_name').val()) {
			showInvalid('#check_sa_lending_person_name', '要選');
			errors.push(errorMessages.borrow__sa_lending_person_required);
			valid = false;
		} else {
			showValid('#check_sa_lending_person_name');
		}
		if (!$('#sa_lending_date').val()) {
			showInvalid('#check_sa_lending_date', '要填');
			errors.push(errorMessages.borrow__sa_lending_date_required);
			valid = false;
		}
		else {
			showValid('#check_sa_lending_date');
		}

		if (
			($('input[name="sa_deposit_take"]:checked').val() == '1' || $('input[name="sa_id_take"]:checked').val() == '1')
			&&
			($('#sa_id_deposit_box_number').val() == null || $('#sa_id_deposit_box_number').val() == -1)
		) {
			showInvalid('#check_id_deposit_box_number', '要選');
			errors.push(errorMessages.borrow__deposit_box_required);
			valid = false;
		} else {
			showValid('#check_id_deposit_box_number');
		}
	}
	else if (status === 'return') {
		if (!$('#sa_return_person_name').val()) {
			showInvalid('#check_sa_return_person_name', '要選');
			errors.push(errorMessages.return__sa_return_person_name_required);
			valid = false;
		} else {
			showValid('#check_sa_return_person_name');
		}

		if (!$('#sa_returned_date').val()) {
			showInvalid('#check_sa_returned_date', '要填');
			errors.push(errorMessages.borrow__sa_lending_date_required);
			valid = false;
		}
		else {
			showValid('#check_sa_returned_date');
		}

		let no_return_item = true;
		G_itemList.forEach((item, value) => {
			if (item.status == LendItem.STATUS_VALUE.LENDING_OUT && item.checked == true) {
				no_return_item = false;
				return;
			}
		});

		if (no_return_item) {
			errors.push(errorMessages.return__item_empty);
			valid = false;
		}
	}

	$errorWindow.html(errors.join('<br>')).toggle(errors.length > 0);
	$('#modal-form').addClass('was-validated');

	return valid;
}

function showInvalid(selector, message) {
	$(selector).addClass('invalid-feedback').text(message);
}

function showValid(selector) {
	$(selector).text('').removeClass('invalid-feedback').addClass('valid-feedback');
}

function setToday(inputId) {
	const today = new Date().toISOString().split('T')[0];
	document.getElementById(inputId).value = today;
}

function getToday() {
	const now = new Date();
	const localDate = new Date(new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Taipei' }).format(now));
	return localDate;
}

function loadBorrowPerson(id) {
	$.ajax({
		type: 'GET',
		url: `/borrow/getData/${id}`,
		success: function (response) {
			// 借用人資訊 (左邊)
			$("#borrow_department").text(response.borrow_department || "--");
			$("#borrow_person_name").text('聯絡人：' + response.borrow_person_name || "--");
			$("#phone").text(response.phone || "--");
			$("#email").text(response.email || "--");
		},
		error: function (error) {
			console.log(error);
		}
	});
}

function loadBorrowItems(id, callback) {
	$.ajax({
		url: '/property/info/getWithBorrowID', // 替換為你的 API URL
		type: 'POST',
		data: {
			borrow_id: id,
			_token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
		},
		success: function (response) {
			// 假設 response 返回新的表格資料，根據需求去更新表格
			//console.log(response.data);
			callback(response.data);
		},
		error: function (error) {
			console.log('Error:', error);
			callback([]);
		}
	});
}

function loadChargePersonList() {
	// 發送 AJAX 
	$.ajax({
		url: '/show-user-name', // 替換為你的 API URL
		type: 'POST',
		data: {
			_token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
		},
		success: function (response) {
			// 假設 response 返回新的表格資料，根據需求去更新表格
			//console.log(response.data);
			let personList = response.data;
			const lending_person_list = $("#sa_lending_person_name");
			const return_person_list = $("#sa_return_person_name");
			lending_person_list.empty(); // 清空舊選項
			return_person_list.empty();

			lending_person_list.append(`<option selected disabled value="">請選擇承辦人</option>`);
			return_person_list.append(`<option selected disabled value="">請選擇承辦人</option>`);
			// 動態添加新選項
			$.each(personList, function (index, person) {
				lending_person_list.append(`<option value="${person.name}">${person.name}</option>`);
				return_person_list.append(`<option value="${person.name}">${person.name}</option>`);
			});
		},
		error: function (error) {
			console.log('Error:', error);
		}
	});
}

function showBorrowItems(lending_property) {
	let constraint_seq = new Set();
	$('#borrow_list').empty(); // 清空容器
	let formattedInfo = '';

	const orderMap = {
		[LendItem.STATUS_VALUE.LENDING_OUT] : 0 ,
		[LendItem.STATUS_VALUE.WAIT_FOR_LEND] : 1 ,
		[LendItem.STATUS_VALUE.RETURNED] : 2 ,
		[LendItem.STATUS_VALUE.BACK_TO_SYS] : 3
	};	//歷史共業
	lending_property.sort((a, b) => {
		return orderMap[a.status] - orderMap[b.status];
	});


	$.each(lending_property, function (index, item) {
		constraint_seq.add(item.status);
		G_itemList.set(item.ssid, new LendItem(item.ssid, item.status));	// 將借用物品加入全域變數陣列中
		let gray_outcome = false;

		let statusBadge = '';
		if (item.status == LendItem.STATUS_VALUE.LENDING_OUT) {
			statusBadge = '<span class="badge bg-success"><i class="bi bi-box-arrow-right"></i> 外借中</span>';
		}
		else if (item.status == LendItem.STATUS_VALUE.WAIT_FOR_LEND) {
			statusBadge = '<span class="badge bg-primary"><i class="bi bi-exclamation-circle-fill"></i> 待借出</span>';
		}
		else if (item.status == LendItem.STATUS_VALUE.RETURNED) {
			statusBadge = '<span class="badge bg-secondary"><i class="bi bi-arrow-return-left"></i> 已歸還</span>';
			gray_outcome = true;
		}
		else if (item.status == LendItem.STATUS_VALUE.BACK_TO_SYS) {
			statusBadge = '<span class="badge bg-danger"><i class="bi bi-backspace"></i> 取消借出</span>';
			gray_outcome = true;
		}

		if(gray_outcome == true){
			gray_outcome = true;
		}
		else{
			gray_outcome = '';
		}

		// Card 版
		let card = `
      <div class="col-md-6 col-lg-6 col-xl-6 mb-3">
        <div class="card h-100 shadow-sm ${gray_outcome}" id="item-${item.ssid}" data-ssid="${item.ssid}" data-status="${item.status}">
          <div class="card-body">
            <div class="d-flex gap-3">
              <img src="/storage/propertyImgs/${item.img_url}" 
                   style="width: 40%; aspect-ratio: 1 / 1; object-fit: contain;" 
                   class="rounded border"
                   alt="No Image"
                   onerror="this.src='https://dummyimage.com/1440x1080/cccccc/000000&text=No+Image';">
              <div class="flex-grow-1">
                  <label class="form-check-label fw-bold" for="${item.ssid}">
                    ${item.name} ${item.second_name || ''}
                  </label>                
                <p class="mb-1">
                  <span class="badge bg-purple">${item.ssid}</span>
                  <span class="badge bg-teal">${item.class}</span>
                </p>
                <p class="mb-1">${statusBadge}</p>
                <p class="card-text small text-muted">
                  規格：${item.format || '無'}<br>
                  ${item.remark ? `備註：${item.remark}` : ''}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>`;

		formattedInfo += card;
	});

	//manuplateConstraint(constraint_seq);

	// 包裝在 row 內，支援 RWD
	$('#borrow_list').append(`<div class="row">${formattedInfo}</div>`);
	G_itemList.forEach((item, value) => {
		item.bindDOM();
	});
}

function loadManuplationData(id) {
	$.ajax({
		url: '/borrow/getData/single', // 替換為你的 API URL
		type: 'POST',
		data: {
			id: id,
			_token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
		},
		success: function (response) {
			//console.log(response.data);
			let single_info = response.data;

			function setInputValue(selector, value) {
				const $el = $(selector);

				if ($el.length === 0) return; // 沒找到直接跳過

				if ($el.is(':radio')) {
					// 如果 selector 是 radio (通常用 name)
					$(`input[name="${$el.attr('name')}"][value="${value}"]`).prop("checked", true);
				}
				else if ($el.is('select') || $el.is('textarea') || $el.is('input')) {
					// 一般 input / select / textarea
					$el.val(value);
				}
			}

			$('#borrow_id').val(id);
			setInputValue('#sa_lending_person_name', single_info.sa_lending_person_name);
			setInputValue('#sa_lending_date', single_info.sa_lending_date);

			setInputValue('#sa_id_deposit_box_number', single_info.sa_id_deposit_box_number);
			setInputValue('#sa_return_person_name', single_info.sa_return_person_name);
			setInputValue('#sa_returned_date', single_info.sa_returned_date);

			setInputValue('#sa_remark', single_info.sa_remark);

			setInputValue('input[name="sa_deposit_take"]', single_info.sa_deposit_take);
			setInputValue('input[name="sa_id_take"]', single_info.sa_id_take);
			setInputValue('input[name="sa_deposit_returned"]', single_info.sa_deposit_returned);
			setInputValue('input[name="sa_id_returned"]', single_info.sa_id_returned);
		},
		error: function (error) {
			console.log('Error:', error);
		}
	});


}

function loadStatusBar(id) {
	// 狀態代號對應文字
	const statusLabels = {
		filling_time: "已填單",
		borrow_date: "外借中",
		sa_returned_date: "已歸還",
		reject_time: "系統拒絕",
		expired: "逾期未歸還"
	};

	// 狀態流程定義
	const statusFlows = {
		0: ["filling_time", "reject_time"],            // 系統拒絕
		2: ["filling_time"],                           // 填單完成，未借出
		1: ["filling_time", "borrow_date"],            // 外借中
		3: ["filling_time", "borrow_date", "sa_returned_date"] // 已歸還
	};

	$.ajax({
		type: 'GET',
		url: `/borrow/getData/${id}`,
		success: function (response) {
			console.log(response);
			let lending_status = response.status;
			let steps = statusFlows[lending_status];

			// 判斷是否逾期（借出中但超過應還日期）
			let isExpired = false;
			if (lending_status === 1 && response.returned_date) {
				let dueDate = new Date(response.returned_date).setHours(0, 0, 0, 0);
				let today = new Date().setHours(0, 0, 0, 0);
				if (today > dueDate) {
					isExpired = true;
					steps.push("expired"); // 插入逾期狀態
				}
			}

			// 動態生成 HTML
			let html = "";
			steps.forEach((stepId, idx) => {
				let label = statusLabels[stepId] || stepId;
				let dateText = response[stepId] || "--";

				// 狀態顏色對應
				let colorClass = "";
				switch (stepId) {
					case "filling_time":
						colorClass = "bg-primary"; // 藍色
						break;
					case "borrow_date":
						colorClass = "bg-success"; // 綠色
						dateText += ' ~ ' + response['returned_date'];
						break;
					case "expired":
						dateText = '原定歸還：' + response['returned_date'];
						colorClass = "bg-warning"; // 黃色
						break;
					case "sa_returned_date":
						colorClass = "bg-secondary"; // 灰色
						break;
					case "reject_time":
						colorClass = "bg-danger"; // 紅色
						break;
					default:
						colorClass = "bg-dark"; // 預設黑色
				}

				html += `
          <div class="step">
            <div class="step-circle ${colorClass} text-white">${idx + 1}</div>
            <div><strong>${label}</strong></div>
            <small class="text-muted">${dateText}</small>
          </div>
        `;

				if (idx < steps.length - 1) {
					html += `<div class="step-line"></div>`;
				}
			});


			$("#borrow_timeline").append(html);
		},
		error: function (error) {
			console.log(error);
		}
	});
}

function loadStatus(id, callback) {
	$.ajax({
		type: 'GET',
		url: `/borrow/getData/${id}`,
		success: function (response) {
			if (response.status) {
				console.log(response.status);
				G_manuplateStatus = CODE_2_STATUS[response.status];
				callback(response.status);
			}
		},
		error: function (error) {
			console.log(error);
		}
	});
}

function manuplateControlArea(mode) {
	// 借用歸還區域
	const LEND_OUT_AREA = new Area(["#sa_lending_person_name", "#sa_lending_date", "#sa_deposit_take_radio1", "#sa_deposit_take_radio2", "#sa_id_take_radio1", "#sa_id_take_radio2", "#lend_out_today_btn"]);
	const RETURN_IN_AREA = new Area(["#sa_return_person_name", "#sa_returned_date", "#sa_deposit_returned_radio1", "#sa_deposit_returned_radio2", "#sa_id_returned_radio1", "#sa_id_returned_radio2", "#return_in_today_btn"]);
	const REMARK_AREA = new Area(['#sa_remark']);
	const ID_BOX_AREA = new Area(['#sa_id_deposit_box_number']);

	// 通用控制區域
	const COMMON_MANUPLATE_AREA = new Area(['#lending-area', '#extend-lending-area', '#email-sending-area']);
	const SAVE_QUIT_MANUPLATE_AREA = new Area(['#double-check-area', '#save-data', '#quit-without-save']);

	// 掃描條碼區域
	const SCAN_AREA = new Area(['#scan_list']);
	const SCAN_TEXT_AREA = new Area(['#scan-check']);

	// 物品借用區域
	const ITEM_AREA = new Area(['#borrow_list']);


	switch (mode) {
		case "borrow":
			// 借用區
			COMMON_MANUPLATE_AREA.hide();
			SAVE_QUIT_MANUPLATE_AREA.show();

			LEND_OUT_AREA.enable();
			REMARK_AREA.enable();
			ID_BOX_AREA.enable();
			SCAN_AREA.enable();
			break;
		case "return":
			// 歸還區 
			RETURN_IN_AREA.enable();
			REMARK_AREA.enable();
			COMMON_MANUPLATE_AREA.hide();
			SAVE_QUIT_MANUPLATE_AREA.show();

			ID_BOX_AREA.disable();
			SCAN_AREA.enable();
			break;
		case 'comment':
			// 僅剩編輯功能
			REMARK_AREA.enable();
			COMMON_MANUPLATE_AREA.hide();
			SAVE_QUIT_MANUPLATE_AREA.show();
			break;
		case 'disabled':
			COMMON_MANUPLATE_AREA.show();
			SAVE_QUIT_MANUPLATE_AREA.hide();
			REMARK_AREA.disable();
			ID_BOX_AREA.disable();
			LEND_OUT_AREA.disable();
			RETURN_IN_AREA.disable();
			SCAN_AREA.disable();

			SCAN_TEXT_AREA.clearText();
			SCAN_AREA.clearValidation();

			G_itemList.forEach((item, value) => {
				item.uncheck();
			});

			break;
	}
	return mode;
}