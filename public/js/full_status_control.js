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
}


$(function () {
  const urlParams = new URLSearchParams(window.location.search);
  const URL_ID = urlParams.get('id');

  if (URL_ID) {
    console.log("目前的 id =", URL_ID);

    // 讀取流程
    loadBorrowPerson(URL_ID);
    loadStatusBar(URL_ID);
    loadBorrowItems(URL_ID, function (lending_property) {
      showBorrowItems(lending_property);
      loadManuplationData(URL_ID);
    });
    loadChargePersonList();
    manuplateControlAreaChange('disabled');
  }

  $('#scan_list').on('input', function () {
    const inputVal = $(this).val();
    if (inputVal.length >= 8) {
      let itemArray = [];

      // 管理員指令碼輸入區
      // if (cheatCodeAction(inputVal)) {
      //   $('#scan_list').val('');
      //   return;
      // }

      // 從 modal 中的 tbody 名稱為 'borrow_list' 的每個 tr 中提取第一個 td 的值
      $('#borrow_list tr').each(function () {
        const firstTdValue = $(this).find('td').eq(0).text().trim(); // 第一個 <td>
        const statusTdValue = $(this).find('td').eq(6).text().trim(); // 第7個 <td>（索引從 0 開始）

        if (statusTdValue !== '退回系統')
          itemArray.push(firstTdValue);

      });

      //console.log(itemArray);

      // 檢查 input 的值是否存在於 itemArray 中
      if (itemArray.includes(inputVal)) {
        // 如果存在，找到對應的 tr 並加上 class 'table-success'
        $('#borrow_list tr').each(function () {
          const firstTdValue = $(this).find('td:first').text().trim();
          if (firstTdValue === inputVal) {
            $(this).addClass('table-success');
            alert("掃描成功");
          }
        });
      } else {
        alert("清單無此物品或此物已退回系統");
      }
      $('#scan_list').val('');
    }
  });

  $('#start-lending').on('click', () => {
    loadStatus(URL_ID, function (status) {
      const code2status = {
        0: "comment",          // 系統拒絕
        1: "returned",  // 歸還
        2: "borrow",    // 借出
        3: "comment"    // 系統歸還
      };
      manuplateControlAreaChange(code2status[status]);
    });
  });

  $('#quit-without-save').on('click', () => {
    manuplateControlAreaChange('disabled');
  });

});

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

  $.each(lending_property, function (index, item) {
    constraint_seq.add(item.status);

    let statusBadge = '';
    if (item.status == 1) {
      statusBadge = '<span class="badge bg-success"><i class="bi bi-box-arrow-right"></i> 外借中</span>';
    }
    else if (item.status == 2) {
      statusBadge = '<span class="badge bg-primary"><i class="bi bi-exclamation-circle-fill"></i> 待借出</span>';
    }
    else if (item.status == 3) {
      statusBadge = '<span class="badge bg-secondary"><i class="bi bi-arrow-return-left"></i> 已歸還</span>';
    }
    else {
      statusBadge = '<span class="badge bg-danger"><i class="bi bi-backspace"></i> 退回系統</span>';
    }

    // Card 版
    let card = `
      <div class="col-md-6 col-lg-6 col-xl-6 mb-3">
        <div class="card h-100 shadow-sm">
          <input type="hidden" value="${item.ssid}">
          <div class="card-body">
            <div class="d-flex gap-3">
              <img src="./storage/propertyImgs/${item.img_url}" 
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
      console.log(response.data);
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
        callback(response.status);
      }
    },
    error: function (error) {
      console.log(error);
    }
  });
}

function manuplateControlAreaChange(mode) {
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
      break;
    case 'MANUAL_TEST':
      // 全部顯示
      $('#lending_out').prop('hidden', false);
      $('#return_in').prop('hidden', false);
      $('#area_sa_id_deposit_box_number').prop('hidden', false);

      $('#area_sa_id_deposit_box_number').prop('disabled', false);
      $('#scan_list').prop('disabled', false);
      $('#sa_remark').prop('disabled', false);
      $('#save-data').prop('disabled', false);
      break;

  }
  return mode;
}