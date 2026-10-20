"use strict";

(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else root.WeaduFeed = factory();
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  const MESSAGES = {
    missing_id: "id is empty.",
    missing_title: "title is empty.",
    title_too_long: "title is longer than 150 characters.",
    title_too_short: "title is under 15 characters.",
    title_promotional: "title contains promotional wording Google often rejects.",
    title_all_caps: "title is all capitals.",
    missing_description: "description is empty.",
    description_too_long: "description is longer than 5000 characters.",
    missing_link: "link is empty.",
    link_not_http: "link must start with http:// or https://.",
    missing_image: "image_link is empty.",
    image_not_http: "image_link must start with http:// or https://.",
    missing_availability: "availability is empty.",
    availability_invalid: "availability must be in_stock, out_of_stock, preorder, or backorder.",
    missing_price: "price is empty.",
    price_unparsed: "price must look like 19.00 EUR.",
    price_not_positive: "price must be greater than zero.",
    price_currency_missing: "price has no ISO currency code.",
    missing_brand: "brand is empty.",
    missing_gtin: "gtin and mpn are both empty.",
    gtin_invalid: "gtin check digit or length is invalid.",
    missing_condition: "condition is empty.",
    condition_invalid: "condition must be new, refurbished, or used.",
    duplicate_id: "id is duplicated in this feed.",
    empty_feed: "No product was found in this text.",
    unrecognized_format: "Paste a Google RSS or Atom feed, or a CSV with a header row.",
    truncated: "Only the first 500 products were checked.",
  };

  const AVAILABILITY = { in_stock: true, out_of_stock: true, preorder: true, backorder: true };
  const CONDITIONS = { new: true, refurbished: true, used: true };
  const PROMO = /\b(free shipping|livraison gratuite|envio gratis|envío gratis|sale|soldes?|%\s?off|discount|réduction|promo)\b/i;
  const DEFAULT_MAX = 500;

  function finding(code, severity, field) {
    return { code: code, severity: severity, field: field, message: MESSAGES[code] || code };
  }

  function charLength(value) {
    return Array.from(String(value || "")).length;
  }

  function plain(value) {
    return String(value || "")
      .replace(/<[^>]*>/g, " ")
      .replace(/&nbsp;/gi, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  function isHttp(value) {
    return /^https?:\/\//i.test(String(value || "").trim());
  }

  function gtinDigits(value) {
    return String(value || "").replace(/[\s-]/g, "");
  }

  function gtinIsValid(value) {
    const digits = gtinDigits(value);
    if (!/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/.test(digits)) return false;
    let sum = 0;
    let weight = 3;
    for (let i = digits.length - 2; i >= 0; i--) {
      sum += Number(digits[i]) * weight;
      weight = weight === 3 ? 1 : 3;
    }
    const expected = (10 - (sum % 10)) % 10;
    return expected === Number(digits[digits.length - 1]);
  }

  function isAllCaps(title) {
    const chars = Array.from(title);
    const letters = [];
    for (let i = 0; i < chars.length; i++) {
      if (/\p{L}/u.test(chars[i])) letters.push(chars[i]);
    }
    if (letters.length < 8) return false;
    for (let i = 0; i < letters.length; i++) {
      const ch = letters[i];
      if (ch !== ch.toUpperCase() || ch === ch.toLowerCase()) return false;
    }
    return true;
  }

  function identifierWaived(value) {
    const flag = String(value || "").trim().toLowerCase();
    return flag === "no" || flag === "false" || flag === "0";
  }

  function suggestTitle(item) {
    const source = item || {};
    const title = String(source.title || "").trim();
    const brand = String(source.brand || "").trim();
    let next = title;
    if (!next && brand) next = brand;
    else if (brand && next && next.toLowerCase().indexOf(brand.toLowerCase()) === -1) next = brand + " " + next;
    if (charLength(next) > 150) next = Array.from(next).slice(0, 150).join("").trim();
    if (next === title) return "";
    return next;
  }

  function priceFindings(price) {
    const raw = String(price == null ? "" : price).trim();
    if (!raw) return [finding("missing_price", "error", "price")];
    const match = raw.match(/^(?:([A-Za-z]{3})\s*)?(\d+(?:[.,]\d+)?)(?:\s*([A-Za-z]{3}))?$/);
    if (!match) return [finding("price_unparsed", "error", "price")];
    const num = Number(match[2].replace(",", "."));
    const currency = match[1] || match[3] || "";
    const found = [];
    if (!(num > 0)) found.push(finding("price_not_positive", "error", "price"));
    if (!currency) found.push(finding("price_currency_missing", "warning", "price"));
    return found;
  }

  function auditItem(item) {
    const source = item || {};
    const id = String(source.id == null ? "" : source.id).trim();
    const title = String(source.title == null ? "" : source.title).trim();
    const description = plain(source.description);
    const link = String(source.link == null ? "" : source.link).trim();
    const image = String(source.image_link == null ? "" : source.image_link).trim();
    const availability = String(source.availability == null ? "" : source.availability)
      .trim()
      .toLowerCase()
      .replace(/\s+/g, "_");
    const brand = String(source.brand == null ? "" : source.brand).trim();
    const gtin = gtinDigits(source.gtin);
    const mpn = String(source.mpn == null ? "" : source.mpn).trim();
    const condition = String(source.condition == null ? "" : source.condition).trim().toLowerCase();
    const findings = [];

    if (!id) findings.push(finding("missing_id", "error", "id"));
    if (!title) findings.push(finding("missing_title", "error", "title"));
    else {
      const length = charLength(title);
      if (length > 150) findings.push(finding("title_too_long", "error", "title"));
      else if (length < 15) findings.push(finding("title_too_short", "warning", "title"));
      if (PROMO.test(title)) findings.push(finding("title_promotional", "warning", "title"));
      if (isAllCaps(title)) findings.push(finding("title_all_caps", "warning", "title"));
    }
    if (!description) findings.push(finding("missing_description", "error", "description"));
    else if (charLength(description) > 5000) findings.push(finding("description_too_long", "error", "description"));
    if (!link) findings.push(finding("missing_link", "error", "link"));
    else if (!isHttp(link)) findings.push(finding("link_not_http", "error", "link"));
    if (!image) findings.push(finding("missing_image", "error", "image_link"));
    else if (!isHttp(image)) findings.push(finding("image_not_http", "error", "image_link"));
    if (!availability) findings.push(finding("missing_availability", "error", "availability"));
    else if (!AVAILABILITY[availability]) findings.push(finding("availability_invalid", "error", "availability"));
    const prices = priceFindings(source.price);
    for (let i = 0; i < prices.length; i++) findings.push(prices[i]);
    if (!brand) findings.push(finding("missing_brand", "warning", "brand"));
    if (!gtin && !mpn && !identifierWaived(source.identifier_exists)) {
      findings.push(finding("missing_gtin", "warning", "gtin"));
    } else if (gtin && !gtinIsValid(gtin)) {
      findings.push(finding("gtin_invalid", "error", "gtin"));
    }
    if (!condition) findings.push(finding("missing_condition", "warning", "condition"));
    else if (!CONDITIONS[condition]) findings.push(finding("condition_invalid", "warning", "condition"));

    return {
      id: id,
      title: title,
      suggestedTitle: suggestTitle(source),
      findings: findings,
    };
  }

  function countReport(items) {
    let errors = 0;
    let warnings = 0;
    for (let i = 0; i < items.length; i++) {
      const findings = items[i].findings;
      for (let j = 0; j < findings.length; j++) {
        if (findings[j].severity === "error") errors++;
        else warnings++;
      }
    }
    return { items: items.length, errors: errors, warnings: warnings };
  }

  function auditItems(items, maxItems) {
    const cap = maxItems == null ? DEFAULT_MAX : maxItems;
    const list = Array.isArray(items) ? items : [];
    const truncated = list.length > cap;
    const slice = truncated ? list.slice(0, cap) : list;
    const seen = {};
    const reports = [];
    for (let i = 0; i < slice.length; i++) {
      const report = auditItem(slice[i]);
      if (report.id) {
        if (seen[report.id]) report.findings.push(finding("duplicate_id", "error", "id"));
        else seen[report.id] = true;
      }
      reports.push(report);
    }
    const errors = [];
    if (!list.length) errors.push(finding("empty_feed", "error", "feed"));
    if (truncated) errors.push(finding("truncated", "warning", "feed"));
    return {
      ok: errors.every(function (item) { return item.severity !== "error"; }) && reports.every(function (item) {
        return item.findings.every(function (row) { return row.severity !== "error"; });
      }),
      format: "items",
      truncated: truncated,
      errors: errors,
      items: reports,
      counts: countReport(reports),
    };
  }

  function decodeXml(value) {
    return String(value || "")
      .replace(/<!\[CDATA\[([\s\S]*?)\]\]>/g, "$1")
      .replace(/&#(\d+);/g, function (_, num) { return String.fromCodePoint(Number(num)); })
      .replace(/&#x([0-9a-fA-F]+);/g, function (_, num) { return String.fromCodePoint(parseInt(num, 16)); })
      .replace(/&lt;/g, "<")
      .replace(/&gt;/g, ">")
      .replace(/&quot;/g, '"')
      .replace(/&apos;/g, "'")
      .replace(/&amp;/g, "&");
  }

  function field(block, name) {
    const re = new RegExp("<(?:[\\w-]+:)?" + name + "\\b[^>]*>([\\s\\S]*?)<\\/(?:[\\w-]+:)?" + name + ">", "i");
    const match = block.match(re);
    return match ? decodeXml(match[1]).trim() : "";
  }

  function parseXmlItems(xml) {
    const blocks = [];
    const re = /<(?:[\w-]+:)?(?:item|entry)\b[^>]*>([\s\S]*?)<\/(?:[\w-]+:)?(?:item|entry)>/gi;
    let match;
    while ((match = re.exec(xml)) !== null) blocks.push(match[1]);
    return blocks.map(function (block) {
      return {
        id: field(block, "id"),
        title: field(block, "title"),
        description: field(block, "description") || field(block, "summary"),
        link: field(block, "link"),
        image_link: field(block, "image_link"),
        availability: field(block, "availability"),
        price: field(block, "price"),
        brand: field(block, "brand"),
        gtin: field(block, "gtin"),
        mpn: field(block, "mpn"),
        condition: field(block, "condition"),
        identifier_exists: field(block, "identifier_exists"),
      };
    });
  }

  function parseCsvRows(text) {
    const source = String(text || "").replace(/^\uFEFF/, "");
    const firstBreak = source.search(/\r?\n/);
    const headerLine = firstBreak === -1 ? source : source.slice(0, firstBreak);
    const commas = (headerLine.match(/,/g) || []).length;
    const tabs = (headerLine.match(/\t/g) || []).length;
    const delimiter = tabs > commas ? "\t" : ",";
    const rows = [];
    let row = [];
    let cell = "";
    let inQuotes = false;
    for (let i = 0; i < source.length; i++) {
      const ch = source[i];
      if (inQuotes) {
        if (ch === '"') {
          if (source[i + 1] === '"') {
            cell += '"';
            i++;
          } else inQuotes = false;
        } else cell += ch;
      } else if (ch === '"') inQuotes = true;
      else if (ch === delimiter) {
        row.push(cell);
        cell = "";
      } else if (ch === "\n" || ch === "\r") {
        if (ch === "\r" && source[i + 1] === "\n") i++;
        row.push(cell);
        cell = "";
        if (row.some(function (value) { return value.trim() !== ""; })) rows.push(row);
        row = [];
      } else cell += ch;
    }
    if (cell.length || row.length) {
      row.push(cell);
      if (row.some(function (value) { return value.trim() !== ""; })) rows.push(row);
    }
    return rows;
  }

  function headerKey(value) {
    return String(value || "")
      .trim()
      .toLowerCase()
      .replace(/^g:/, "")
      .replace(/[\s-]+/g, "_");
  }

  function parseCsvItems(text) {
    const rows = parseCsvRows(text);
    if (!rows.length) return null;
    const headers = rows[0].map(headerKey);
    if (headers.indexOf("title") === -1 && headers.indexOf("id") === -1) return null;
    const items = [];
    for (let r = 1; r < rows.length; r++) {
      const record = {};
      for (let c = 0; c < headers.length; c++) record[headers[c]] = rows[r][c] == null ? "" : String(rows[r][c]).trim();
      items.push({
        id: record.id || "",
        title: record.title || "",
        description: record.description || "",
        link: record.link || "",
        image_link: record.image_link || record.image || "",
        availability: record.availability || "",
        price: record.price || "",
        brand: record.brand || "",
        gtin: record.gtin || "",
        mpn: record.mpn || "",
        condition: record.condition || "",
        identifier_exists: record.identifier_exists || "",
      });
    }
    return items;
  }

  function auditFeedText(text, maxItems) {
    const raw = String(text || "").trim();
    if (!raw) {
      const empty = auditItems([], maxItems);
      empty.format = "unknown";
      return empty;
    }
    const looksXml = raw.charAt(0) === "<" || /<(?:rss|feed|item|entry)\b/i.test(raw);
    if (looksXml) {
      const items = parseXmlItems(raw);
      const report = auditItems(items, maxItems);
      report.format = /<feed\b/i.test(raw) ? "atom" : "rss";
      if (!items.length) {
        report.errors = [finding("empty_feed", "error", "feed")];
        report.ok = false;
      }
      return report;
    }
    const csvItems = parseCsvItems(raw);
    if (!csvItems) {
      return {
        ok: false,
        format: "unknown",
        truncated: false,
        errors: [finding("unrecognized_format", "error", "feed")],
        items: [],
        counts: { items: 0, errors: 0, warnings: 0 },
      };
    }
    const report = auditItems(csvItems, maxItems);
    report.format = "csv";
    return report;
  }

  return {
    MESSAGES: MESSAGES,
    gtinIsValid: gtinIsValid,
    suggestTitle: suggestTitle,
    auditItems: auditItems,
    auditFeedText: auditFeedText,
  };
});
