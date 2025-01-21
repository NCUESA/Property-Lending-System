@extends('layouts.template')

@section('content')
    <img src="./ncuesa_img.png" alt="NCUESA_IMG" class="center"
        style="max-width: 100%; height: auto; display: block; margin: 0 auto;">

    <h2 class="name-cn logo-title">國立彰化師範大學學生會 器材借用表單</h2>
    <div class="alert alert-dark" role="alert">
        <h4 class="alert-heading">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                class="bi bi-info-square" viewBox="0 0 16 16">
                <path
                    d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z" />
                <path
                    d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0" />
            </svg>注意事項
        </h4>
        <ul>
            <li>器材借用時間最長二個禮拜。預先借用請勿填寫此表</li>
            <li>借用器材時需押一證件與押金100元</li>
            <li>器材借用如遺失或判定為人損，借用人應負賠償責任</li>
            <li>押金與證件於器材歸還後退還</li>
        </ul>
    </div>
    <hr>
    <form id='borrow' class="needs-validation" novalidate>
        <h4>基本資料填寫</h4>
        <div class="form-group row">
            <label for="know_filling" class="col-sm-2 col-form-label">你知道你自己在填現場借用表嗎？</label>
            <div class="col">
                <div class="form-check form-check">
                    <input class="form-check-input position-static" type="radio" name="know_filling" value="y"
                        aria-label="..." required>知道
                </div>
                <div class="form-check form-check">
                    <input class="form-check-input position-static" type="radio" name="know_filling" value="n"
                        aria-label="..." required>不知道
                    <div class="valid-feedback" id='check_know_filling'></div>
                </div>
            </div>
        </div>
        <hr>
        <div class="form-group row">
            <label for="borrow_place" class="col-sm-2 col-form-label">借用地點</label>
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
            <label for="department">單位/系所</label>
            <input type="input" class="form-control" id="department" required>
            <div class="valid-feedback" id='check_department'>

            </div>
        </div>
        <div class="form-group">
            <label for="contact_person">聯絡人姓名</label>
            <input type="input" class="form-control" id="contact_person" required>
            <div class="valid-feedback" id='check_contact_person'>

            </div>
        </div>
        <div class="form-group">
            <label for="phone">聯絡電話</label>
            <input type="input" class="form-control" id="phone" required maxlength="10" min="10">
            <div class="valid-feedback" id='check_phone'>

            </div>
        </div>
        <div class="form-group">
            <label for="email">電子郵件</label>
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
                                
                                



                                
                                                            <table class='table'>
                                                                <thead>
                                                                    <th scope="col">外借</th>
                                                                    <th scope="col">財產編號</th>
                                                                    <th scope="col">財產分類</th>
                                                                    <th scope="col">財產名稱</th>
                                                                    <th scope="col">第二名稱</th>
                                                                    <th scope="col">置放地點</th>
                                                                    <th scope="col">規格</th>
                                                                    <th scope="col">備註</th>
                                                                    <th scope="col">財產照片</th>
                                                                </thead>
                                                                <tbody id='borrowable_item'>

                                                                </tbody>
                                                                <tfoot>
                                                                    <td colspan="9">
                                                                        <span class="invalid-feedback" id='check_borrow_item'>請至少選擇一個物品！
                                                                        </span>
                                                                    </td>
                                                                </tfoot>
                                                            </table>
                                                        
                            </div>-->
            </div>

            <hr>

            <div class="d-grid gap-2">
                <button type="submit" id='send_form' class="btn btn-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-send" viewBox="0 0 16 16">
                        <path
                            d="M15.854.146a.5.5 0 0 1 .11.54l-5.819 14.547a.75.75 0 0 1-1.329.124l-3.178-4.995L.643 7.184a.75.75 0 0 1 .124-1.33L15.314.037a.5.5 0 0 1 .54.11ZM6.636 10.07l2.761 4.338L14.13 2.576zm6.787-8.201L1.591 6.602l4.339 2.76z" />
                    </svg>送出借用</button>
                <button type="reset" id='send_form' class="btn btn-outline-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-arrow-clockwise" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2z" />
                        <path
                            d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466" />
                    </svg>取消重填
                </button>
            </div>


    </form>
    <hr>
@endsection
