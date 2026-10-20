"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const { execFileSync } = require("node:child_process");

const utm = require("./utm");
const rsa = require("./rsa");
const feed = require("./feed");

const fixtures = JSON.parse(fs.readFileSync(path.join(__dirname, "fixtures.json"), "utf8"));

function codes(findings) {
  return findings.map(function (row) { return row.code; }).sort();
}

test("utm fixtures", function () {
  for (const row of fixtures.utm) {
    const built = utm.buildUtmUrl(row.input);
    assert.equal(built.ok, row.ok, row.name);
    if (row.ok) assert.equal(built.url, row.url, row.name);
    else assert.deepEqual(codes(built.errors), row.codes.slice().sort(), row.name);
  }
});

test("utm inspect and ledger", function () {
  const raw = "See https://shop.example/p?utm_source=google&utm_medium=cpc.";
  const urls = utm.extractUrls(raw);
  assert.deepEqual(urls, ["https://shop.example/p?utm_source=google&utm_medium=cpc"]);
  assert.deepEqual(codes(utm.inspectUrl(urls[0])), ["missing_utm_campaign"]);

  const input = { url: "https://shop.example/p", source: "google", medium: "cpc", campaign: "spring" };
  const row = utm.ledgerRow(input, "2026-10-16");
  assert.match(row, /2026-10-16/);
  assert.match(row, /utm_campaign=spring/);

  const note = "Brief\n";
  const once = utm.appendLedger(note, row);
  assert.match(once, /## UTM ledger/);
  const twice = utm.appendLedger(once, row);
  const occurrences = twice.split(row).length - 1;
  assert.equal(occurrences, 2);
  assert.equal(twice.split("## UTM ledger").length - 1, 1);
});

test("rsa width, lint, and candidates", function () {
  assert.equal(rsa.googleAdsWidth("Shop"), 4);
  assert.equal(rsa.googleAdsWidth("広告"), 4);
  const bang = rsa.lintAdLine("Shop now!", "headline");
  assert.ok(bang.codes.includes("headline_exclamation"));
  const wide = rsa.lintAdLine("This headline is definitely too long for Google", "headline");
  assert.ok(wide.codes.includes("over_limit"));
  const candidates = rsa.buildRsaCandidates({
    product: "Cotton shirt",
    benefit: "Soft cotton you can wear every day without thinking about it",
    offer: "20% off",
    cta: "Shop",
  });
  assert.ok(candidates.length >= 3);
  for (const candidate of candidates) {
    assert.ok(candidate.width <= candidate.limit, candidate.text);
    assert.equal(candidate.text.includes("best"), false);
  }
  const onlyProduct = rsa.buildRsaCandidates({ product: "Cotton shirt" });
  assert.equal(onlyProduct.length, 1);
  assert.equal(onlyProduct[0].text, "Cotton shirt");
});

test("feed item fixtures", function () {
  for (const row of fixtures.items) {
    const report = feed.auditItems([row.item]);
    assert.equal(report.items.length, 1, row.name);
    assert.deepEqual(codes(report.items[0].findings), row.codes.slice().sort(), row.name);
  }
});

test("title suggestions and duplicate ids", function () {
  for (const row of fixtures.titles) {
    assert.equal(feed.suggestTitle(row.item), row.suggested, row.name);
  }
  const long = "A".repeat(160);
  assert.equal(feed.suggestTitle({ title: long, brand: "" }).length, 150);
  const batch = feed.auditItems([
    fixtures.items[0].item,
    Object.assign({}, fixtures.items[0].item),
  ]);
  assert.deepEqual(codes(batch.items[1].findings), ["duplicate_id"]);
  const truncated = feed.auditItems([fixtures.items[0].item, fixtures.items[1].item], 1);
  assert.equal(truncated.truncated, true);
  assert.equal(truncated.items.length, 1);
  assert.ok(codes(truncated.errors).includes("truncated"));
});

test("rss, atom, and csv parsers", function () {
  const rss = [
    '<?xml version="1.0"?>',
    '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">',
    "<channel><item>",
    "<g:id>1</g:id>",
    "<g:title><![CDATA[Acme Cotton Shirt]]></g:title>",
    "<g:description>A cotton shirt for everyday wear.</g:description>",
    "<g:link>https://shop.example/p/1</g:link>",
    "<g:image_link>https://shop.example/i/1.jpg</g:image_link>",
    "<g:availability>in_stock</g:availability>",
    "<g:price>29.00 EUR</g:price>",
    "<g:brand>Acme</g:brand>",
    "<g:gtin>4006381333931</g:gtin>",
    "<g:condition>new</g:condition>",
    "</item></channel></rss>",
  ].join("");
  const parsed = feed.auditFeedText(rss);
  assert.equal(parsed.format, "rss");
  assert.deepEqual(codes(parsed.items[0].findings), []);

  const csv = [
    "id,title,description,link,image_link,availability,price,brand,gtin,condition",
    '"8","Blue cotton shirt, soft","Soft cotton shirt for everyday wear.","https://shop.example/p/8","https://shop.example/i/8.jpg","in stock","29.00 EUR","Acme","4006381333931","new"',
  ].join("\n");
  const csvReport = feed.auditFeedText(csv);
  assert.equal(csvReport.format, "csv");
  assert.equal(csvReport.items[0].title, "Blue cotton shirt, soft");
  assert.deepEqual(codes(csvReport.items[0].findings), []);
  assert.equal(feed.auditFeedText("hello").format, "unknown");
  assert.equal(feed.gtinIsValid("4006381333931"), true);
  assert.equal(feed.gtinIsValid("4006381333932"), false);
});

test("php port", function () {
  execFileSync("php", [path.join(__dirname, "feed_audit_test.php")], { stdio: "inherit" });
});
