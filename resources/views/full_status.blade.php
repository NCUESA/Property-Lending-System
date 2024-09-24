@extends('layouts.template')

@section('content')
    <h2>器材借用狀況</h2>
    <hr>
    <div class="alert alert-success" role="alert">
        <h4 class="alert-heading">注意事項</h4>
        <ul>
            <li>依照地點分顏色，寶山灰色、進德青色</li>
            <li>借用器材請用條碼機掃描器材上方條碼</li>
            <!--<li></li>
            <li></li>-->
        </ul>
    </div>
    <hr>
    <select class="form-control" id='location'>
        <option disabled selected>請選擇地點...</option>
        <option value="jinde">進德</option>
        <option value="baosan">寶山</option>
        <option value="all">全部地點</option>
    </select>
    <div>
        <div class="modal fade" id="modal" tabindex="-1" aria-labelledby="modal_Label"
            aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="">借用清單</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <table class="table">
                                <thead>
                                    <th scope="col">SSID</th>
                                    <th scope="col">財產名稱</th>
                                    <th scope="col">第二名稱</th>
                                    <th scope="col">財產類別</th>
                                    <th scope="col">規格</th>
                                    <th scope="col">備註</th>
                                    <th scope="col">當前狀態</th>
                                    <th scope="col">財產圖片</th>
                                </thead>
                                <tbody id="borrow_list">
                                    
                                </tbody>
                            </table>
                        </div>
                        <hr>
                        <div class="container">
                            <form>
                                <div class="row" style="padding-top: 1rem;">
                                    <h4 style="font-weight: bold;">條碼識別區</h4>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">操作(借用還是歸還)</label>
                                            <select class="form-select form-select mb-3" id="sa_manuplate" required
                                                disabled>
                                                <option selected disabled value="">請選擇..</option>
                                                <option value="borrow">借用</option>
                                                <option value="return">歸還</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-3">
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
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                    </div>
                                    <hr>
                                    <h4 style="font-weight: bold;">借出填寫區</h4>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">借出承辦人</label>
                                            <select class="form-select form-select mb-3" id="sa_lending_person_name"
                                                required disabled>
                                                <option selected disabled value="">請選擇承辦人</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">借出經辦日期</label>
                                            <input type="date" class="form-control" placeholder="" id="sa_lending_date"
                                                required disabled value="">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">押金收取</label>
                                            <select class="form-select form-select mb-3" id="sa_deposit_take" required
                                                disabled>
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
                                            <select class="form-select form-select mb-3" id="sa_id_take" required
                                                disabled>
                                                <option selected disabled value="">請選擇</option>
                                                <option value="1">收了 YES</option>
                                                <option value="0">沒收 NO</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">押金證件盒編號</label>
                                            <select class="form-select form-select mb-3" id="sa_id_deposit_box_number"
                                                required disabled>
                                                <option selected disabled value="">請選擇</option>
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
                                        <hr>
                                    </div>

                                    <hr>
                                    <h4 style="font-weight: bold;">歸還填寫區</h4>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">歸還承辦人</label>
                                            <select class="form-select form-select mb-3" id="sa_return_person_name"
                                                required disabled>
                                                <option selected disabled value="">請選擇承辦人</option>
                                                <option value=""></option>
                                            </select>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">歸還經辦日期</label>
                                            <input type="date" class="form-control" placeholder=""
                                                id="sa_returned_date" disabled>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">押金退還</label>
                                            <select class="form-select form-select mb-3" id="sa_deposit_returned"
                                                required disabled>
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
                                            <select class="form-select form-select mb-3" id="sa_id_returned" required
                                                disabled>
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

                                    <div class="row" style="padding-top: 1rem;">
                                        <div class="col-md-12">
                                            <label class="form-label">備註</label>
                                            <div class="form-floating">
                                                <textarea disabled class="form-control" placeholder="請在這邊詳細填寫" id="sa_remark" style="height: 150px" required></textarea>
                                                <div class="invalid-feedback">
                                                    必填
                                                </div>
                                                <label for="reply_textarea">請在這邊詳細填寫</label>
                                            </div>
                                        </div>
                                    </div>
                            </form>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="start-lending">開始填寫</button>
                        <button type="button" class="btn btn-success" id="save-data">填寫完成(儲存資料)</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">關閉視窗(不儲存關閉)</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <table class="table">
        <thead>
            <tr>
                <th scope="col">流水號</th>
                <th scope="col">借出承辦人</th>
                <th scope="col">借出日期</th>
                <th scope="col">押金收取</th>
                <th scope="col">證件收取</th>
                <th scope="col">證件押金盒編號</th>

                <th scope="col">歸還承辦人</th>
                <th scope="col">歸還日期</th>
                <th scope="col">押金退還</th>
                <th scope="col">證件退還</th>
                <th scope="col">備註</th>

                <th scope="col">填單時間</th>
                <th scope="col">Email</th>
                <th scope="col">借用單位</th>
                <th scope="col">聯絡人</th>
                <th scope="col">聯絡電話</th>
                <th scope="col">借用日期</th>
                <th scope="col">預計歸還日期</th>
                <th scope="col">器材清單</th>
            </tr>
        </thead>
        <tbody id='lending_status'>

        </tbody>
    </table>
@endsection
