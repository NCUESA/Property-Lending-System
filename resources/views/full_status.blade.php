@extends('layouts.template')

@section('content')
    <h2>器材借用狀況</h2>
    <hr>

    <form>
        <div class="row g-3 align-items-center">
            <div class="col-4 d-grid gap-2">
                <button class="btn btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#lendingInfo"
                    aria-expanded="false" aria-controls="lendingInfo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-info-circle-fill" viewBox="0 0 16 16">
                        <path
                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2" />
                    </svg>
                    注意事項
                </button>
            </div>
            <div class="col-4 d-grid gap-2">
                <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#searchInfo"
                    aria-expanded="false" aria-controls="searchInfo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search"
                        viewBox="0 0 16 16">
                        <path
                            d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0" />
                    </svg>
                    進階查詢
                </button>
            </div>
            <!--<div class="col-1">
                                                        <label for="place" class="col-form-label"></label>
                                                    </div>-->
            <div class="col-4">
                <select class="form-control btn btn-secondary" id='place'>
                    <option disabled selected>快速查詢</option>
                    <option value="jinde">進德</option>
                    <option value="baosan">寶山</option>
                    <!--<option value="all">全部地點</option>-->
                </select>
            </div>
        </div>
    </form>

    <div class="collapse" id="lendingInfo">
        <div class="alert alert-success" role="alert">
            <h4 class="alert-heading">注意事項</h4>
            <ul>
                <li>借用器材請用條碼機掃描器材上方條碼</li>
                <li>掃描時請切換成<strong>英文輸入法</strong></li>
                <li>如果逼了沒有反應，請點一下輸入框</li>
                <li><strong>請一定要選擇是借出還是歸還</strong></li>
                <li><strong>請一定要選擇是借出還是歸還</strong></li>
                <li><strong>請一定要選擇是借出還是歸還</strong></li>
            </ul>
        </div>
    </div>
    <div class="collapse" id="searchInfo">
        <h4>查詢</h4>
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
                <div class="col-sm-3 d-grid gap-2">
                    <button type="reset" class="btn btn-danger btn-block" id="reset_search_query">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                            class="bi bi-x-circle" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                            <path
                                d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708" />
                        </svg>取消重填</button>
                </div>
                <div class="col-sm-3 d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-block">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                            class="bi bi-search" viewBox="0 0 16 16">
                            <path
                                d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0" />
                        </svg>查詢</button>
                </div>
            </div>
        </form>
    </div>
    <hr>
    
    <div> <!-- Filter -->
    簡單過濾->
        <button type="button" class="badge btn btn-primary" id="waiting">藍色：待借出</button>
        <button type="button" class="badge btn btn-success" id="lend_out">綠色：外借中</button>
        <button type="button" class="badge btn btn-warning" id="out_of_time">黃色：逾期未還</button>
        <button type="button" class="badge btn btn-secondary" id="returned">灰色：已歸還</button>
        <button type="button" class="badge btn btn-danger" id="banned">紅色：借用遭管理員拒絕</button>
    </div>
    <div>
        <div class="modal fade" id="modal" tabindex="-1" aria-labelledby="modal_Label" aria-hidden="true">
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
                                            <select class="form-select form-select mb-3" id="sa_id_take" required disabled>
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
                                        <hr>
                                    </div>

                                    <hr>
                                    <h4 style="font-weight: bold;">歸還填寫區</h4>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">歸還承辦人</label>
                                            <select class="form-select form-select mb-3" id="sa_return_person_name" required
                                                disabled>
                                                <option selected disabled value="">請選擇承辦人</option>
                                                <option value=""></option>
                                            </select>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">歸還經辦日期</label>
                                            <input type="date" class="form-control" placeholder="" id="sa_returned_date"
                                                disabled>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">押金退還</label>
                                            <select class="form-select form-select mb-3" id="sa_deposit_returned" required
                                                disabled>
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
                                                <textarea disabled class="form-control" placeholder="請在這邊詳細填寫"
                                                    id="sa_remark" style="height: 150px" required></textarea>
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
                        <button type="button" class="btn btn-danger" id="modal-close"
                            data-bs-dismiss="modal">關閉視窗(不儲存關閉)</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <table class="table">
        <thead>
            <tr>
                <!--<th scope="col">流水號</th>
                                                                            <th scope="col">借出承辦人</th>
                                                                            <th scope="col">借出日期</th>
                                                                            <th scope="col">押金收取</th>
                                                                            <th scope="col">證件收取</th>
                                                                            <th scope="col">證件押金盒編號</th>

                                                                            <th scope="col">歸還承辦人</th>
                                                                            <th scope="col">歸還日期</th>
                                                                            <th scope="col">押金退還</th>
                                                                            <th scope="col">證件退還</th>
                                                                            <th scope="col">備註</th>-->

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

    <!--
            <div class="toast-container" >
                <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" id="toast1">
                    <div class="toast-header">

                        <strong class="me-auto">Bootstrap</strong>
                        <small class="text-muted">just now</small>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        See? Just like this.
                    </div>
                </div>

                <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">

                        <strong class="me-auto">Bootstrap</strong>
                        <small class="text-muted">2 seconds ago</small>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        Heads up, toasts will stack automatically
                    </div>
                </div>
            </div>-->
@endsection