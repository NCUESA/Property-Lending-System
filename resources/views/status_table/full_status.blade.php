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
                    <i class="bi bi-funnel"></i>
                    進階查詢
                </button>
            </div>

            <div class="col-4 d-grid gap-2">
                <div class="btn-group" role="group" aria-label="">
                    <input type="radio" class="btn-check" id="all" name="place" autocomplete="off" value="all">
                    <label class="btn btn-outline-dark" for="all">全部</label>
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
                        <input type="radio" class="btn-check" id="waiting" name="classStatusRadio"
                            autocomplete="off" value="waiting">
                        <label class="btn btn-outline-primary" for="waiting"><i class="bi bi-hourglass"></i> 待借</label>
                        <input type="radio" class="btn-check" id="lend_out" name="classStatusRadio"
                            autocomplete="off" value="lend_out">
                        <label class="btn btn-outline-success" for="lend_out"><i class="bi bi-box-arrow-right"></i>
                            外借</label>
                        <input type="radio" class="btn-check" id="out_of_time" name="classStatusRadio"
                            autocomplete="off" value="out_of_time">
                        <label class="btn btn-outline-warning" for="out_of_time"><i class="bi bi-calendar2-x"></i>
                            逾期</label>
                        <input type="radio" class="btn-check" id="returned" name="classStatusRadio"
                            autocomplete="off" value="returned">
                        <label class="btn btn-outline-secondary" for="returned"><i class="bi bi-arrow-return-left"></i>
                            已還</label>
                        <input type="radio" class="btn-check" id="banned" name="classStatusRadio"
                            autocomplete="off" value="banned">
                        <label class="btn btn-outline-danger" for="banned"><i class="bi bi-slash-circle"></i>
                            退還</label>
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

    <div class="d-flex justify-content-center visually-hidden" id="loading">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    
    <div class="shadow p-3 mb-5 bg-body rounded">
        <div class="card mb-2 text-dark">
            <div class="card-body py-2">
                <div class="row align-items-center">
                    <div class="col-md-2"><strong>填單時間</strong></div>
                    <div class="col-md-2"><strong>借用期間</strong></div>
                    <div class="col-md-2"><strong>借用單位</strong></div>
                    <div class="col-md-1"><strong>聯絡人</strong></div>
                    <div class="col-md-2"><i class="bi bi-telephone"></i> <strong>聯絡電話</strong></div>
                    <div class="col-md-2"><i class="bi bi-envelope"></i> <strong>Email</strong></div>
                    <div class="col-md-1"><strong>詳細資訊</strong></div>
                </div>
            </div>
        </div>
        <div id="lending_status">
            
        </div>
    </div>

    <nav aria-label="Page navigation example" class="d-flex justify-content-between align-items-center">
        <span id="totalRecords">總共有 4 筆</span>
        <ul class="pagination mb-0" id="pagination"></ul>
    </nav>
@endsection
