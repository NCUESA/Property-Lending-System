@extends('layouts.template')

@section('content')
    <h2>器材借用狀況</h2>
    <div class="row">
        <div class="col-6">
            <select class="form-control" id='location'>
                <option disabled selected>請選擇地點...</option>
                <option value="all">全部地點</option>
                <option value="jinde">進德</option>
                <option value="baosan">寶山</option>
                <!--<option value="307" disabled>307附屬倉庫</option>
                    <option value="parliament" disabled>403議會辦公室</option>-->
            </select>
        </div>
        <div class="col-6">
            <select class="form-control" id='lending_status'>
                <option disabled selected>查看借用狀況...</option>
                <option value="all">全部器材</option>
                <option value="borrowable">可借用</option>
                <option value="lent">已被借用</option>
            </select>
        </div>
    </div>
    <hr>
    <div class="row row-cols-1 row-cols-md-4 g-3" id="property_status">
        <!--<div class="col">
            <div class="card">
                <input type="hidden" value="">
                <img src="https://dummyimage.com/1920x1920/cccccc/000000&text=No+Image" class="card-img-top rounded"
                    alt="">
                <div class="card-body">
                    <input type="checkbox" value="" id="">動圈式麥克風
                    <p><span class="badge bg-primary">什項用具</span></p>
                    <p class="card-text">
                        財產編號：J2000001<br>
                        規格：Carol E dur-916S 珍珠白
                    </p>
                </div>
            </div>
            <div class="card-footer">
                <small class="text-muted">存放地點：進德</small>
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
                    <th scope="col">規格</th>

                    <th scope="col">借用單位</th>
                    <th scope="col">借用日期</th>
                    <th scope="col">預計歸還日期</th>
                    <th scope="col">現場是否可借</th>
                    <th scope="col">參考圖片</th>
                </tr>
            </thead>
            <tbody id='property_status'>
                <tr>

                </tr>
            </tbody>
        </table>-->
@endsection
