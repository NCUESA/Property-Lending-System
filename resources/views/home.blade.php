@extends('layouts.template')

@section('content')
    <img src="./ncuesa_img.png" alt="NCUESA_IMG" class="center">
    <h2 class="name-cn" style="text-align: center;font-size: 3vw">國立彰化師範大學學生會 器材借用表單</h2>
    <div class="alert alert-dark" role="alert">
        <h4 class="alert-heading">注意事項</h4>
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
        <div>
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
        </div>

        <button type="submit" id='send_form' class="btn btn-success">送出借用</button>
        <button type="reset" id='send_form' class="btn btn-danger">取消重填</button>
    </form>
    <hr>
@endsection
