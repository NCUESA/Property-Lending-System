<?php

use App\Models\User;

test('訪客與借用人從首頁導向借用申請', function () {
    $this->get(route('home'))->assertRedirect(route('borrow.create'));

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('borrow.create'));
});

test('值班人員以上從首頁導向後台', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->get(route('home'))
        ->assertRedirect(route('admin.borrows.index'));
});
