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
                <button type="submit" class="btn btn-success btn-block">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-wrench-adjustable" viewBox="0 0 16 16">
                        <path d="M16 4.5a4.5 4.5 0 0 1-1.703 3.526L13 5l2.959-1.11q.04.3.041.61" />
                        <path
                            d="M11.5 9c.653 0 1.273-.139 1.833-.39L12 5.5 11 3l3.826-1.53A4.5 4.5 0 0 0 7.29 6.092l-6.116 5.096a2.583 2.583 0 1 0 3.638 3.638L9.908 8.71A4.5 4.5 0 0 0 11.5 9m-1.292-4.361-.596.893.809-.27a.25.25 0 0 1 .287.377l-.596.893.809-.27.158.475-1.5.5a.25.25 0 0 1-.287-.376l.596-.893-.809.27a.25.25 0 0 1-.287-.377l.596-.893-.809.27-.158-.475 1.5-.5a.25.25 0 0 1 .287.376M3 14a1 1 0 1 1 0-2 1 1 0 0 1 0 2" />
                    </svg>送出新增(調整)</button>
            </div>
            <div class="col-2 d-grid gap-2">
                <button type="reset" class="btn btn-danger btn-block">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-x-circle" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                        <path
                            d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708" />
                    </svg>取消重填</button>
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
