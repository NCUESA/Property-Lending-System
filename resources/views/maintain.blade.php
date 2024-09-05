@extends('layouts.template')

@section('content')
    <h2>器材清單維護</h2>
    <hr>
    <h4>地點查詢</h4>

    <select class="col-6 form-control" id='place'>
        <option disabled selected>請選擇地點...</option>
        <option value="jinde">進德</option>
        <option value="baosan">寶山</option>
        <option value="307">307倉庫</option>
        <option value="403">403議會辦公室</option>
        <option value="all">查看全部</option>
    </select>
    <hr>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal"
        data-property="">新增財產</button>
    <hr>
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
                        <div class="container">
                            <form id="update_property">
                                <div class="row" style="padding-top: 1rem;">
                                    <h4 style="font-weight: bold;">基本資訊</h4>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">財產編號</label>
                                            <input type="input" class="form-control" placeholder="" id="ssid"
                                                value="" maxlength="8" required>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">財產分類</label>
                                            <input type="input" class="form-control" placeholder="" id="class"
                                                value="" required>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">校方資產</label>
                                            <select class="form-select form-select mb-3" id="school_property" required>
                                                <option selected value="1">是</option>
                                                <option selected value="0">否</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">取得日期</label>
                                            <input type="date" class="form-control" placeholder="" id="get_day"
                                                required value="">
                                        </div>

                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">財產名稱</label>
                                            <input type="input" class="form-control" placeholder="" id="name"
                                                value="" required>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">第二名稱</label>
                                            <input type="input" class="form-control" placeholder="" id="second_name"
                                                value="">
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                    </div>
                                    <hr>
                                    <h4 style="font-weight: bold;">狀態資訊</h4>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">允借與否</label>
                                            <select class="form-select form-select mb-3" id="enable_lending" required>
                                                <option selected value="1">是</option>
                                                <option selected value="0">否</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">置放地點</label>
                                            <select class="form-select form-select mb-3" id="belong_place" required>
                                                <option selected value="jinde">進德</option>
                                                <option selected value="baosan">寶山</option>
                                                <option selected value="307">307倉庫</option>
                                                <option selected value="405">405議會辦公室</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">使用單位</label>
                                            <input type="input" class="form-control" placeholder="" id="department"
                                                required value="">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">保管人</label>
                                            <input type="input" class="form-control" placeholder="" id="depositary"
                                                required value="">
                                        </div>


                                    </div>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">金額</label>
                                            <input type="input" class="form-control" placeholder="" id="price"
                                                required value="">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">財產序</label>
                                            <select class="form-select form-select mb-3" id="order_number" required>
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
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">上傳照片</label>
                                            <input type="file" class="form-control" placeholder="" id="prop_img"
                                                value="" accept=".jpg,.jpeg,.png,.webp">
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                        <div class="col-md-3" hidden>
                                            <label class="form-label">Primary Key(禁填)</label>
                                            <input type="input" class="form-control" placeholder="" id="primary_key"
                                                required value="" disabled readonly>
                                        </div>
                                    </div>

                                    <hr>
                                    <div class="row" style="padding-top: 1rem;">
                                        <div class="col-md-12">
                                            <label class="form-label">規格</label>
                                            <div class="form-floating">
                                                <textarea class="form-control" placeholder="上限50字元" id="format" style="height: 150px"></textarea>
                                                <div class="invalid-feedback">
                                                    必填
                                                </div>
                                                <label for="reply_textarea">請在這邊詳細填寫</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row" style="padding-top: 1rem;">
                                        <div class="col-md-12">
                                            <label class="form-label">備註</label>
                                            <div class="form-floating">
                                                <textarea class="form-control" placeholder="上限250字元" id="remark" style="height: 150px"></textarea>
                                                <div class="invalid-feedback">
                                                    必填
                                                </div>
                                                <label for="reply_textarea">請在這邊詳細填寫</label>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="row">
                                        <div class="col-md-12">
                                            <label class="form-label">財產照片</label>
                                            <img src="" alt="No IMG" id="img_url" class="img-fluid">
                                            <div class="invalid-feedback">
                                                必填
                                            </div>
                                        </div>
                                    </div>
                            </form>
                        </div>
                    </div>
                    <div class="modal-footer">
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
                <th scope="col">財產編號</th>
                <th scope="col">財產分類</th>
                <th scope="col">財產名稱</th>
                <th scope="col">第二名稱</th>
                <th scope="col">是否為校方資產</th>
                <th scope="col">外借情形</th>
                <th scope="col">序號</th>
                <th scope="col">金額</th>
                <th scope="col">使用單位</th>
                <th scope="col">保管人</th>
                <th scope="col">置放地點</th>
                <th scope="col">取得日期</th>
                <th scope="col">規格</th>
                <th scope="col">備註</th>
                <th scope="col">財產照片</th>
            </tr>
        </thead>
        <tbody id='property-table'>

        </tbody>
    </table>
@endsection
