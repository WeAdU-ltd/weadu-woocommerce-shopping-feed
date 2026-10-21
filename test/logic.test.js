"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const { execFileSync } = require("node:child_process");


const fixtures = JSON.parse(fs.readFileSync(path.join(__dirname, "fixtures.json"), "utf8"));

function codes(findings) {
  return findings.map(function (row) { return row.code; }).sort();
}

test("php port", function () {
  execFileSync("php", [path.join(__dirname, "feed_audit_test.php")], { stdio: "inherit" });
});
