<?php

use Essentio\Core\Helper;

beforeEach(function () {
    $this->helper = new Helper("/base/path");
});

it("resolves relative paths from base", function () {
    expect($this->helper->fromBase("file.txt"))->toBe("/base/path/file.txt");
    expect($this->helper->fromBase("/nested/file.txt"))->toBe("/base/path/nested/file.txt");
    expect($this->helper->fromBase(""))->toBe("/base/path/");
});

it("returns non-strings unchanged in autoCast", function () {
    expect($this->helper->autoCast(123))->toBe(123);
    expect($this->helper->autoCast(true))->toBeTrue();
    expect($this->helper->autoCast(null))->toBeNull();
    expect($this->helper->autoCast(["array"]))->toBe(["array"]);
});

it("unquotes quoted strings in autoCast", function () {
    expect($this->helper->autoCast('"quoted"'))->toBe("quoted");
    expect($this->helper->autoCast("'also quoted'"))->toBe("also quoted");
});

it("casts booleans correctly in autoCast", function () {
    expect($this->helper->autoCast("true"))->toBeTrue();
    expect($this->helper->autoCast("false"))->toBeFalse();
});

it("casts null correctly in autoCast", function () {
    expect($this->helper->autoCast("null"))->toBeNull();
});

it("casts integers in autoCast", function () {
    expect($this->helper->autoCast("42"))->toBe(42);
    expect($this->helper->autoCast("-10"))->toBe(-10);
});

it("casts floats in autoCast", function () {
    expect($this->helper->autoCast("3.14"))->toBe(3.14);
    expect($this->helper->autoCast("-2.71"))->toBe(-2.71);
    expect($this->helper->autoCast("1e3"))->toBe(1000.0);
});

it("returns original string if no match in autoCast", function () {
    expect($this->helper->autoCast("hello"))->toBe("hello");
    expect($this->helper->autoCast("42a"))->toBe("42a");
});
