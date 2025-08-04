@extends('layouts.template')

@section('content')
    <h2>器材借用總表</h2>
    <hr>

    <form>
        <div class="row g-3 align-items-center">
            <div class="col-4 d-grid gap-2">
                <button class="btn btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#lendingInfo"
                    aria-expanded="false" aria-controls="lendingInfo">
                    <i class="bi bi-info-circle"></i>
                    注意事項
                </button>
            </div>
            <div class="col-4 d-grid gap-2">
                <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#searchInfo"
                    aria-expanded="false" aria-controls="searchInfo">
                    <i class="bi bi-search"></i>
                    進階查詢
                </button>
            </div>

            <div class="col-4 d-grid gap-2">
                <div class="btn-group" role="group" aria-label="">
                    <input type="radio" class="btn-check" id="jinde" name="place" autocomplete="off" value="jinde">
                    <label class="btn btn-outline-dark" for="jinde">進德</label>
                    <input type="radio" class="btn-check" id="baosan" name="place" autocomplete="off" value="baosan">
                    <label class="btn btn-outline-dark" for="baosan">寶山</label>
                </div>
            </div>
        </div>
    </form>

    <div class="collapse" id="lendingInfo">
        <div class="alert alert-success" role="alert">
            <h4 class="alert-heading">注意事項</h4>
            <ol>
                <li>借用器材請用條碼機掃描器材上方條碼</li>
                <li>如果逼了沒有反應，請點一下輸入框</li>
                <li>操作說明可以參考
                    <a
                        href="https://hackmd.io/@NCUESA/HkS_fHB2kl#%E5%A6%82%E6%9E%9C%E4%BD%A0%E5%9C%A8%E5%80%BC%E5%8B%A4">這裡有一個使用手冊</a>
                </li>
                <li>請不要去改系統自己帶入的資料，不然你會被管理員Bonk</li>
            </ol>
        </div>
    </div>
    <div class="collapse" id="searchInfo">
        <h4>進階查詢</h4>
        <form id="search">
            <div class="row g-3 align-items-center">
                <label for="search_contact" class="col-sm-1 col-form-label">借用聯絡人</label>
                <div class="col-sm-2">
                    <input type="text" class="form-control" id="search_contact" value="">
                </div>

                <label for="search_property" class="col-sm-1 col-form-label">借用器材</label>
                <div class="col-sm-2">
                    <input type="text" class="form-control" id="search_property" value="">
                </div>

                <label for="search_lendout" class="col-sm-1 col-form-label">借出日期</label>
                <div class="col-sm-2">
                    <input type="date" class="form-control" id="search_lendout" value="">
                </div>

                <label for="search_return" class="col-sm-1 col-form-label">歸還日期</label>
                <div class="col-sm-2">
                    <input type="date" class="form-control" id="search_return" value="">
                </div>
                <label for="search_department" class="col-sm-1 col-form-label">借用單位</label>
                <div class="col-sm-2">
                    <input type="text" class="form-control" id="search_department" value="">
                </div>

                <label for="search_prepare_return" class="col-sm-1 col-form-label">預計歸還日期</label>
                <div class="col-sm-2">
                    <input type="date" class="form-control" id="search_prepare_return" value="">
                </div>
                <label for="" class="col-sm-1 col-form-label">篩選借用狀態</label>
                <div class="col-sm-5 d-grid gap-2">
                    <div class="btn-group" role="group" aria-label="">
                        <input type="radio" class="btn-check" id="all" name="btnradio" autocomplete="off"
                            value="">
                        <label class="btn btn-outline-info" for="all"><i class="bi bi-three-dots"></i> 全部</label>
                        <input type="radio" class="btn-check" id="waiting" name="btnradio" autocomplete="off"
                            value="waiting">
                        <label class="btn btn-outline-primary" for="waiting"><i class="bi bi-hourglass"></i> 待借</label>
                        <input type="radio" class="btn-check" id="lend_out" name="btnradio" autocomplete="off"
                            value="lend_out">
                        <label class="btn btn-outline-success" for="lend_out"><i class="bi bi-box-arrow-right"></i>
                            外借</label>
                        <input type="radio" class="btn-check" id="out_of_time" name="btnradio" autocomplete="off"
                            value="out_of_time">
                        <label class="btn btn-outline-warning" for="out_of_time"><i class="bi bi-calendar2-x"></i>
                            逾期</label>
                        <input type="radio" class="btn-check" id="returned" name="btnradio" autocomplete="off"
                            value="returned">
                        <label class="btn btn-outline-secondary" for="returned"><i class="bi bi-arrow-return-left"></i>
                            已還</label>
                        <input type="radio" class="btn-check" id="banned" name="btnradio" autocomplete="off"
                            value="banned">
                        <label class="btn btn-outline-danger" for="banned"><i class="bi bi-slash-circle"></i>
                            借用遭拒</label>
                    </div>
                </div>


                <div class="col-sm-3 d-grid gap-2">
                    <button type="reset" class="btn btn-danger btn-block" id="reset_search_query">
                        <i class="bi bi-arrow-clockwise"></i> 取消重填</button>
                </div>
                <div class="col-sm-3 d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="bi bi-search"></i> 查詢</button>
                </div>
            </div>
        </form>
    </div>
    <hr>

    <div>
        <div class="modal fade" id="modal" tabindex="-1" aria-labelledby="modal_Label" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="">借用清單</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <table class="table">
                                <thead>
                                    <th scope="col">EID</th>
                                    <th scope="col">名稱</th>
                                    <th scope="col">第二名稱</th>
                                    <th scope="col">類別</th>
                                    <th scope="col">規格</th>
                                    <th scope="col">器材備註</th>
                                    <th scope="col">當前狀態</th>
                                    <th scope="col">財產圖片</th>
                                </thead>
                                <tbody id="borrow_list">

                                </tbody>
                            </table>
                        </div>
                        <hr>
                        <div class="container">
                            <form class='needs-validation' id='modal-form'>
                                <div class="row" id="area-first-check-scan">
                                    <h4 style="font-weight: bold;">條碼識別區</h4>
                                    <div class="col-md-3" id="area_sa_manuplate" hidden>
                                        <label class="form-label">操作(借用還是歸還)</label>
                                        <select class="form-select form-select mb-3" id="sa_manuplate" required>
                                            <option selected value="">請選擇..</option>
                                            <option value="borrow">借用</option>
                                            <option value="return">歸還</option>
                                        </select>
                                        <div class="valid-feedback" id="check_sa_manuplate">
                                        </div>
                                    </div>
                                    <div class="col-md-3" id="area_borrow_id" hidden>
                                        <label class="form-label">借用編號(系統自動帶入)</label>
                                        <input type="input" class="form-control" placeholder="此處請勿填寫" id="borrow_id"
                                            value="" disabled>
                                        <div class="invalid-feedback">
                                            必填
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">掃描條碼ID</label>
                                        <input type="input" class="form-control scan_list" placeholder="此處請勿填寫"
                                            id="scan_list" maxlength="8" disabled>
                                    </div>
                                    <div class="col-md-2" id="area_sa_id_deposit_box_number" hidden>
                                        <label class="form-label">押金證件盒編號</label>
                                        <select class="form-select form-select mb-3" id="sa_id_deposit_box_number"
                                            required>
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
                                        <div class="invalid-feedback">
                                            必填
                                        </div>
                                    </div>
                                </div>
                                <div id='lending_out' hidden>
                                    <hr>
                                    <h4 style="font-weight: bold;">借出填寫區</h4>
                                    <h6>(甚麼都沒填會直接將器材退回系統)</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">借出承辦人</label>
                                            <select class="form-select form-select mb-3" id="sa_lending_person_name"
                                                required>
                                                <option selected disabled value="">請選擇承辦人</option>
                                            </select>
                                            <div class="valid-feedback" id="check_sa_lending_person_name">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">借出經辦日期</label>
                                            <input type="date" class="form-control" placeholder=""
                                                id="sa_lending_date" value="">
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">押金收取</label>
                                            <select class="form-select form-select mb-3" id="sa_deposit_take" required>
                                                <option selected disabled value="">請選擇</option>
                                                <option value="1">收了 YES</option>
                                                <option value="0">沒收 NO</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">證件收取</label>
                                            <select class="form-select form-select mb-3" id="sa_id_take" required>
                                                <option selected disabled value="">請選擇</option>
                                                <option value="1">收了 YES</option>
                                                <option value="0">沒收 NO</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>

                                        <hr>
                                    </div>
                                </div>

                                <div id='return_in' hidden>
                                    <hr>
                                    <h4 style="font-weight: bold;">歸還填寫區</h4>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">歸還承辦人</label>
                                            <select class="form-select form-select mb-3" id="sa_return_person_name"
                                                required>
                                                <option selected value="">請選擇承辦人</option>
                                                <option value=""></option>
                                            </select>
                                            <div class="valid-feedback" id="check_sa_return_person_name">

                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">歸還經辦日期</label>
                                            <input type="date" class="form-control" placeholder=""
                                                id="sa_returned_date">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">押金退還</label>
                                            <select class="form-select form-select mb-3" id="sa_deposit_returned"
                                                required>
                                                <option selected disabled value="">請選擇</option>
                                                <option value="1">退了 YES</option>
                                                <option value="0">沒退 NO</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">證件退還</label>
                                            <select class="form-select form-select mb-3" id="sa_id_returned" required>
                                                <option selected disabled value="">請選擇</option>
                                                <option value="1">退了 YES</option>
                                                <option value="0">沒退 NO</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <hr>
                                    </div>
                                </div>

                                <div class="row" id="area-first-check-remark">
                                    <div class="col-md-12">
                                        <label class="form-label">備註</label>
                                        <div class="form-floating">
                                            <textarea disabled class="form-control" placeholder="請在這邊詳細填寫" id="sa_remark" style="height: 150px"></textarea>
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
                    <div class="modal-footer">
                        <div class="container">
                            <div class="row" id="area-first-check-button">
                                <div class="col-2 d-grid gap-2s">
                                    <button type="button" class="btn btn-primary" id="start-lending">
                                        <i class="bi bi-pencil"></i> 開始填寫</button>
                                </div>
                                <div class="col-6"></div>
                                <div class="col-2">
                                    <span class="d-grid gap-2" data-bs-toggle="tooltip" title="要先點開始借用才能按">
                                        <button type="button" class="btn btn-success" id="save-data" disabled
                                            data-bs-toggle="tooltip" data-bs-placement="left" title="要先點開始借用才能按">
                                            <i class="bi bi-floppy2"></i> 儲存</button>
                                    </span>
                                </div>
                                <div class="col-2 d-grid gap-2">
                                    <button type="button" class="btn btn-danger" id="modal-close"
                                        data-bs-dismiss="modal">
                                        <i class="bi bi-x-circle"></i> 關閉(不儲存)</button>
                                </div>
                            </div>
                            <div class="row" id="double-check-area">
                                <div class="col-2 d-grid gap-2">
                                    <button type="button" class="btn btn-success" id="double-check-save">
                                        <i class="bi bi-check-circle"></i> 我非常確定</button>
                                </div>
                                <div class="col-8"></div>
                                
                                <div class="col-2 d-grid gap-2">
                                    <button type="button" class="btn btn-danger" id="double-check-close">
                                        <i class="bi bi-x-circle"></i> 我不確定</button>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="d-flex justify-content-center visually-hidden" id="loading">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    <table class="table table-bordered" id='lending_table' hidden>
        <thead>
            <tr>
                <th scope="col">填單時間</th>
                <th scope="col">借用單位</th>
                <th scope="col" style="display: none">借用日期</th>
                <th scope="col">預計歸還日期</th>
                <th scope="col">聯絡人</th>
                <th scope="col">聯絡電話</th>
                <th scope="col">Email</th>
                <th scope="col">器材清單</th>
            </tr>
        </thead>
        <tbody id='lending_status'>

        </tbody>
    </table>
@endsection
