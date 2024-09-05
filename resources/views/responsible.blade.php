@extends('layouts/template')

@section('content')
    <h2>人員清單管理</h2>

    <hr>
    <form id='add-person'>
        <h6><strong>請注意，此輸入並不防呆，送出前請先再三確認!!!</strong></h6>
        <div class="row g-3 align-items-center">
            <div class="col-1">
                <label for="add_person" class="col-form-label">人員新增</label>
            </div>
            <div class="col-3">
                <input type="input" id="add_person" class="form-control" placeholder="輸入姓名" required> 
            </div>
            <div class="col-1">
                <label for="add_person" class="col-form-label">狀態</label>
            </div>
            <div class="col-3">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status" value="u" id="up" required>
                    <label class="form-check-label" for="up">
                        UP
                    </label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status" value="d" id="down" required>
                    <label class="form-check-label" for="down">
                        DOWN
                    </label>
                </div>
            </div>
            <div class="col-2 d-grid gap-2">
                <button type="submit" class="btn btn-success btn-block">送出新增(調整)</button>
            </div>
            <div class="col-2 d-grid gap-2">
                <button type="reset" class="btn btn-danger btn-block">取消重填</button>
            </div>
        </div>
    </form>
    <table class="table">
        <thead>
            <tr>
                <th scope="col">人員名稱</th>
                <th scope="col">狀態</th>
                <th scope="col">異動</th>
            </tr>
        </thead>
        <tbody id='people_status'>
            <tr>

            </tr>
        </tbody>
    </table>
@endsection
