@extends('layouts.template')

@section('content')
    <img src="./ncuesa_img.png" alt="NCUESA_IMG" class="center"
        style="max-width: 100%; height: auto; display: block; margin: 0 auto;">

    <h2 class="name-cn logo-title">國立彰化師範大學學生會 器材借用表單</h2>
    <div class="alert alert-dark" role="alert">
        <h4 class="alert-heading">
            <i class="bi bi-info-square"></i> 注意事項
        </h4>
        <ul>
            <li>器材借用時間最長二個禮拜。<strong>目前不開放預借</strong></li>
            <li>借用器材時需<strong>押證件乙張與押金NTD 100元</strong> (證件種類：學生證、健保卡、駕照、身分證等)</li>
            <li>器材借用如遺失或判定為人損，借用人應負賠償責任</li>
            <li>確認器材歸還完畢後將退回押金與證件</li>
        </ul>
    </div>
    <hr>
    <form id='borrow' class="needs-validation" novalidate>
        <h4>基本資料填寫</h4>
        <div class="form-group row">
            <label for="know_filling" class="col-sm-2 col-form-label"><i class="bi bi-question-square"></i>
                你知道你自己在填現場借用表嗎？</label>
            <div class="col">
                <div class="form-check form-check">
                    <input class="form-check-input position-static" type="radio" name="know_filling" value="y"
                        aria-label="..." required>
                    <i class="bi bi-emoji-heart-eyes"></i> 知道
                </div>
                <div class="form-check form-check">
                    <input class="form-check-input position-static" type="radio" name="know_filling" value="n"
                        aria-label="..." required>
                    <i class="bi bi-emoji-dizzy"></i> 不知道
                    <div class="valid-feedback" id='check_know_filling'></div>
                </div>
            </div>
        </div>
        <hr>
        <div class="form-group row">
            <label for="borrow_place" class="col-sm-2 col-form-label">
                <i class="bi bi-building"></i> 借用地點</label>
            <div class="col">
                <div class="form-check form-check">
                    <input class="form-check-input position-static" type="radio" name="borrow_place" value="jinde"
                        aria-label="..." required>進德
                </div>
                <div class="form-check form-check">
                    <input class="form-check-input position-static" type="radio" name="borrow_place" value="baosan"
                        aria-label="..." required>寶山
                    <div class="valid-feedback" id='check_borrow_place'></div>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label for="department">
                <i class="bi bi-bookmark-star"></i> 單位/系所</label>
            <input type="input" class="form-control" id="department" placeholder="社團借用請填社團名稱" required>
            <div class="valid-feedback" id='check_department'>

            </div>
        </div>
        <div class="form-group">
            <label for="contact_person">
                <i class="bi bi-file-earmark-person"></i> 聯絡人姓名</label>
            <input type="input" class="form-control" id="contact_person" required>
            <div class="valid-feedback" id='check_contact_person'>

            </div>
        </div>
        <div class="form-group">
            <label for="phone">
                <i class="bi bi-telephone"></i> 聯絡電話</label>
            <input type="input" class="form-control" id="phone" required maxlength="10" min="10">
            <div class="valid-feedback" id='check_phone'>

            </div>
        </div>
        <div class="form-group">
            <label for="email">
                <i class="bi bi-envelope"></i> 電子郵件</label>
            <input type="email" class="form-control" id="email" required>
            <div class="valid-feedback" id='check_email'>
            </div>
        </div>

        <hr>
        <h4>借用物品欄位</h4>
        <div class="form-group">
            <label for="borrow_date">借用日期</label>
            <input type="date" class="form-control" id="borrow_date" required>
            <div class="valid-feedback" id='check_borrow_date'>
            </div>
        </div>
        <div class="form-group">
            <label for="return_date">歸還日期</label>
            <input type="date" class="form-control" id="return_date" required>
            <div class="valid-feedback" id='check_return_date'>
            </div>
        </div>
        <hr>
        <div class="form-group" style='margin-bottom: 1rem;'>
            <label for="find">
                <i class="bi bi-search"></i> 查詢物品名稱</label>
            <select class="form-control" id="find">
            </select>
        </div>
        <div class="container">
            <div class="row row-cols-1 row-cols-md-4 g-3" id="borrowable_item">
                <!-- <div class="col">
                                                                                                    <div class="card">
                                                                                                        <input type="hidden" value="">
                                                                                                        <img src="https://dummyimage.com/1920x1920/cccccc/000000&text=No+Image" class="card-img-top rounded" alt="">
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
                                                                                            </div>-->



            </div>

            <hr>

            <div class="d-grid gap-2">
                <button type="button" id='send_form' class="btn btn-success">
                    <i class="bi bi-send"></i> 送出借用</button>
                <button type="reset" id='' class="btn btn-outline-danger">
                    <i class="bi bi-arrow-clockwise"></i> 取消重填
                </button>
            </div>


    </form>
    <hr>
@endsection