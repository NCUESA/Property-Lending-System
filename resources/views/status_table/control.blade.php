@extends('layouts.template')

@section('content')
    <style>
        .timeline .step {
            text-align: center;
            flex: 1;
        }

        .timeline .step-circle {
            width: 36px;
            height: 36px;
            line-height: 36px;
            border-radius: 50%;
            margin: 0 auto 0.5rem;
            background: #dee2e6;
            color: #495057;
            font-weight: bold;
        }

        .timeline .completed .step-circle {
            background: var(--bs-success);
            color: #fff;
        }

        .timeline .active .step-circle {
            background: var(--bs-primary);
            color: #fff;
        }

        .timeline .expired .step-circle {
            background: var(--bs-warning);
            color: #fff;
        }

        .timeline .reject .step-circle {
            background: var(--bs-danger);
            color: #fff;
        }

        .timeline .step-line {
            flex: 0 0 40px;
            height: 2px;
            background: #ccc;
            align-self: center;
        }
    </style>
    <h2 style="display: inline">操作頁面</h2>
    <h4 style="display: inline"> Control Panel</h4>
    <hr>
    <div class="modal" tabindex="-1" id="loading-modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loading-modal-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                            aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" id="loading-progress-bar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid g-3">
        <div>
            <h4 style="font-weight: bold;"><i class="bi bi-credit-card-2-front"></i> 借用資訊</h4>
            <div class="row g-4">
                <!-- 左邊 借用人資訊 -->
                <div class="col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex gap-3">
                                <div class="flex-grow-1">
                                    <h4 class="form-check-label fw-bold" id='borrow_department'></h4>
                                    <label class="form-check-label fw-bold" id='borrow_person_name'></label>
                                    <p class="mb-1">
                                        <span class="badge bg-secondary" id='phone'></span>
                                        <span class="badge bg-success" id='email'></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 右邊 橫向流程狀態 -->
                <div class="col-lg-8">
                    <div class="timeline d-flex justify-content-between align-items-center flex-wrap" id="borrow_timeline">
                        <!-- JS 動態塞 -->
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <div class="row g-3">
            <div class="col-lg-4 col-12">
                <!-- #region -->
                <!-- Control Panel -->
                <!-- #endregion -->
                <div class="alert alert-secondary">
                    <form class='needs-validation' id='modal-form'>
                        <h4 style="font-weight: bold;"><i class="bi bi-git"></i> 通用操作</h4>
                        <div class="row">
                            <div class="col-md-6" id="area_borrow_id">
                                <label class="form-label">借用編號(系統自動帶入)</label>
                                <input type="input" class="form-control" placeholder="此處請勿填寫" id="borrow_id"
                                    value="" disabled>
                                <div class="invalid-feedback">
                                    必填
                                </div>
                            </div>

                            <div class="col-md-6" id="area_sa_id_deposit_box_number">
                                <label class="form-label">押金證件盒編號</label>
                                <select class="form-select form-select mb-3" id="sa_id_deposit_box_number">
                                    <option selected disabled value="-1">請選擇</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                    <option value="6">6</option>
                                    <option value="7">7</option>
                                    <option value="8">8</option>
                                    <option value="9">9</option>
                                    <option value="10">10</option>
                                    <option value="11">11</option>
                                    <option value="12">12</option>
                                    <option value="13">13</option>
                                    <option value="14">14</option>
                                    <option value="15">15</option>
                                    <option value="16">16</option>
                                    <option value="17">17</option>
                                    <option value="18">18</option>
                                    <option value="19">19</option>
                                    <option value="20">20</option>
                                    <option value="21">21</option>
                                    <option value="22">22</option>
                                    <option value="23">23</option>
                                    <option value="24">24</option>

                                </select>
                                <div class="invalid-feedback" id="check_id_deposit_box_number">
                                    必填
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-4" id="lending-area">
                                <label class="form-label">操作按鈕</label>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-primary" id="start-lending">
                                        <i class="bi bi-pencil"></i> 填寫</button>
                                </div>
                            </div>
                            <!--
                                                    <div class="col-4" id="extend-lending-area">
                                                        <label class="form-label">展延借用期限</label>
                                                        <div class="d-grid gap-2">
                                                            <button type="button" class="btn btn-warning" id="extend-lending">
                                                                <i class="bi bi-arrow-left-right"></i> 展延</button>
                                                        </div>
                                                    </div>
                                                    <div class="col-4" id="email-sending-area">
                                                        <label class="form-label">寄送提醒Email</label>
                                                        <div class="d-grid gap-2">
                                                            <button type="button" class="btn btn-light" id="extend-lending">
                                                                <i class="bi bi-envelope-arrow-up"></i> 寄送</button>
                                                        </div>
                                                    </div>-->
                        </div>
                        <div class="row" id="double-check-area">
                            <div class="col-6">
                                <label class="form-label">返回功能表</label>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-danger" id="quit-without-save">
                                        <i class="bi bi-arrow-90deg-left"></i> 返回(不儲存)</button>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label">儲存填寫內容</label>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-success" id="save-data">
                                        <i class="bi bi-floppy2"></i> 儲存</button>
                                </div>
                            </div>
                        </div>

                        <div id='lending_out'>
                            <hr>
                            <h4 style="font-weight: bold;"><i class="bi bi-arrow-up-right-circle"></i> 借出填寫區</h4>
                            <h6>(甚麼都沒填會直接將器材退回系統)</h6>
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label">借出承辦人 <i class="bi bi-person-fill-gear"></i></label>
                                    <select class="form-select form-select mb-3" id="sa_lending_person_name" required>
                                        <option selected disabled value="">請選擇承辦人</option>
                                    </select>
                                    <div class="valid-feedback" id="check_sa_lending_person_name">
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <label for="sa_lending_date" class="form-label">
                                        借出經辦日期 <i class="bi bi-calendar-date"></i>
                                    </label>

                                    <div class="input-group">
                                        <input type="date" class="form-control" id="sa_lending_date"
                                            name="sa_lending_date" required>
                                        <button class="btn btn-primary" type="button"
                                            onclick="setToday('sa_lending_date')" id="lend_out_today_btn">今天</button>
                                    </div>

                                    <div class="invalid-feedback" id="check_sa_lending_date">
                                        
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 col-sm-12">
                                    <label class="form-label">押金收取 <i class="bi bi-cash-coin"></i></label>

                                    <div class="btn-group" role="group" aria-label="sa_deposit_take_radio">
                                        <input type="radio" class="btn-check" name="sa_deposit_take"
                                            id="sa_deposit_take_radio1" autocomplete="off" value="1">
                                        <label class="btn btn-outline-success" for="sa_deposit_take_radio1">收了
                                            Take</label>

                                        <input checked="checked" type="radio" class="btn-check" name="sa_deposit_take"
                                            id="sa_deposit_take_radio2" autocomplete="off" value="0">
                                        <label class="btn btn-outline-danger" for="sa_deposit_take_radio2">沒收 No</label>
                                    </div>

                                    <div class="invalid-feedback">
                                        必填
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-12">
                                    <label class="form-label">證件收取 <i class="bi bi-person-vcard"></i></label>

                                    <div class="btn-group" role="group" aria-label="sa_id_take_radio">
                                        <input type="radio" class="btn-check" name="sa_id_take" id="sa_id_take_radio1"
                                            autocomplete="off" value="1">
                                        <label class="btn btn-outline-success" for="sa_id_take_radio1">收了 Take</label>

                                        <input checked="checked" type="radio" class="btn-check" name="sa_id_take"
                                            id="sa_id_take_radio2" autocomplete="off" value="0">
                                        <label class="btn btn-outline-danger" for="sa_id_take_radio2">沒收 No</label>
                                    </div>
                                    <div class="invalid-feedback">
                                        必填
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div id='return_in'>

                            <h4 style="font-weight: bold;"><i class="bi bi-arrow-down-left-circle"></i> 歸還填寫區</h4>
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label">歸還承辦人 <i class="bi bi-person-fill-gear"></i></label>
                                    <select class="form-select form-select mb-3" id="sa_return_person_name" required>
                                        <option selected value="">請選擇承辦人</option>
                                        <option value=""></option>
                                    </select>
                                    <div class="valid-feedback" id="check_sa_return_person_name">

                                    </div>
                                </div>

                                <div class="col-md-7">
                                    <label for="sa_returned_date" class="form-label">
                                        歸還經辦日期 <i class="bi bi-calendar-date"></i>
                                    </label>

                                    <div class="input-group">
                                        <input type="date" class="form-control" id="sa_returned_date"
                                            name="sa_returned_date" required>
                                        <button class="btn btn-primary" type="button"
                                            onclick="setToday('sa_returned_date')" id="return_in_today_btn">今天</button>
                                    </div>

                                    <div class="invalid-feedback" id="check_sa_returned_date">
                                        必填
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">押金退還 <i class="bi bi-cash-coin"></i></label>

                                    <div class="btn-group" role="group" aria-label="sa_deposit_returned_radio">
                                        <input type="radio" class="btn-check" name="sa_deposit_returned"
                                            id="sa_deposit_returned_radio1" autocomplete="off" value="1">
                                        <label class="btn btn-outline-success" for="sa_deposit_returned_radio1">退了
                                            Back</label>

                                        <input checked="checked" type="radio" class="btn-check"
                                            name="sa_deposit_returned" id="sa_deposit_returned_radio2" autocomplete="off"
                                            value="0">
                                        <label class="btn btn-outline-danger" for="sa_deposit_returned_radio2">沒退
                                            No</label>
                                    </div>

                                    <div class="invalid-feedback">
                                        必填
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">證件退還 <i class="bi bi-person-vcard"></i></label>
                                    <div class="btn-group" role="group" aria-label="sa_id_returned_radio">
                                        <input type="radio" class="btn-check" name="sa_id_returned"
                                            id="sa_id_returned_radio1" autocomplete="off" value="1">
                                        <label class="btn btn-outline-success" for="sa_id_returned_radio1">退了 Back</label>

                                        <input checked="checked" type="radio" class="btn-check" name="sa_id_returned"
                                            id="sa_id_returned_radio2" autocomplete="off" value="0">
                                        <label class="btn btn-outline-danger" for="sa_id_returned_radio2">沒退
                                            No</label>
                                    </div>
                                    <div class="invalid-feedback">
                                        必填
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row" id="area-first-check-remark">

                            <div class="col-md-12">
                                <label class="form-label">備註</label>
                                <div class="form-floating">
                                    <textarea class="form-control" placeholder="請在這邊詳細填寫" id="sa_remark" style="height: 150px"></textarea>
                                    <div class="invalid-feedback">
                                        必填
                                    </div>
                                    <label for="reply_textarea">請在這邊詳細填寫</label>
                                </div>
                            </div>
                        </div>
                        <div class="row" id='area-double-check-confirm-text' hidden>
                            <div class="col-md-12">
                                <h3 style="text-align: center"><strong>請確認掃描結果！</strong></h3>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-8 col-12">
                <!-- #region -->
                <!-- Item Panel -->
                <!-- #endregion -->
                <div class="alert alert-primary">
                    <h4 style="font-weight: bold;"><i class="bi bi-card-heading"></i> 借用清單</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">掃描條碼ID</label>
                            <input type="input" class="form-control scan_list" placeholder="此處請勿填寫" id="scan_list"
                                maxlength="8">
                            <div class="invalid-feedback" id="scan-check">

                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div id="borrow_list">

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="position-fixed bottom-0 end-0 p-3">
        <div class="toast bg-danger" role="alert" aria-live="assertive" aria-atomic="true" id="error_message_window">
            <div class="toast-header">
                <strong class="me-auto"><i class="bi bi-emoji-dizzy-fill"></i> 錯誤 Error</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body text-white" id='error_message'>
            </div>
        </div>
    </div>
    <div class="position-fixed bottom-0 end-0 p-3">
        <div class="toast bg-success" role="alert" aria-live="assertive" aria-atomic="true" id="info_message_window">
            <div class="toast-header">
                <strong class="me-auto"><i class="bi bi-emoji-smile-fill"></i> 填寫完畢 Looks Great</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body text-white">
                確認輸入OK後，再按一次儲存即可
            </div>
        </div>
    </div>
    <div class="position-fixed bottom-0 end-0 p-3">
        <div class="toast bg-primary" role="alert" aria-live="assertive" aria-atomic="true" id="wait_message_window">
            <div class="toast-header">
                <strong class="me-auto"><i class="bi bi-upload"></i> 處理中 Uploading</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body text-white">
                請稍後...
            </div>
        </div>
    </div>
    <div class="position-fixed bottom-0 end-0 p-3">
        <div class="toast bg-success" role="alert" aria-live="assertive" aria-atomic="true" id="done_message_window">
            <div class="toast-header">
                <strong class="me-auto"><i class="bi bi-check-lg"></i> 完成 Finish</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body text-white" id="done_message">
                 
            </div>
        </div>
    </div>
@endsection
