"use strict";

const UTM_ORDER = ["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term"];
const VALUE_RE = /^[A-Za-z0-9._~-]+$/;
const MAX_VALUE = 80;

const FIELD_TO_PARAM = {
  source: "utm_source",
  medium: "utm_medium",
  campaign: "utm_campaign",
  content: "utm_content",
  term: "utm_term",
};

function utmMessage(code) {
  const messages = {
    url_required: "Landing URL is required.",
    url_invalid: "Landing URL is not a valid URL.",
    url_protocol: "Landing URL must start with http:// or https://.",
    utm_source_required: "utm_source is required.",
    utm_medium_required: "utm_medium is required.",
    utm_campaign_required: "utm_campaign is required.",
    utm_value_invalid: "Use letters, digits, and . _ ~ - with no spaces (80 characters max).",
    missing_utm_source: "utm_source is missing.",
    missing_utm_medium: "utm_medium is missing.",
    missing_utm_campaign: "utm_campaign is missing.",
    duplicate_utm_source: "utm_source is repeated.",
    duplicate_utm_medium: "utm_medium is repeated.",
    duplicate_utm_campaign: "utm_campaign is repeated.",
    empty_utm_source: "utm_source is empty.",
    empty_utm_medium: "utm_medium is empty.",
    empty_utm_campaign: "utm_campaign is empty.",
  };
  return messages[code] || code;
}

function error(code, field) {
  return { code: code, field: field, message: utmMessage(code) };
}

function normalizeValue(raw, field, required, errors) {
  const value = String(raw == null ? "" : raw).trim();
  if (!value) {
    if (required) errors.push(error("utm_" + field + "_required", field));
    return "";
  }
  if (value.length > MAX_VALUE || !VALUE_RE.test(value)) {
    errors.push(error("utm_value_invalid", field));
    return "";
  }
  return value.toLowerCase();
}

function buildUtmUrl(input) {
  const sourceInput = input || {};
  const errors = [];
  const rawUrl = String(sourceInput.url == null ? "" : sourceInput.url).trim();
  if (!rawUrl) errors.push(error("url_required", "url"));

  const values = {
    source: normalizeValue(sourceInput.source, "source", true, errors),
    medium: normalizeValue(sourceInput.medium, "medium", true, errors),
    campaign: normalizeValue(sourceInput.campaign, "campaign", true, errors),
    content: normalizeValue(sourceInput.content, "content", false, errors),
    term: normalizeValue(sourceInput.term, "term", false, errors),
  };

  let parsed = null;
  if (rawUrl) {
    try {
      parsed = new URL(rawUrl);
    } catch (caught) {
      errors.push(error("url_invalid", "url"));
    }
    if (parsed && parsed.protocol !== "http:" && parsed.protocol !== "https:") {
      errors.push(error("url_protocol", "url"));
      parsed = null;
    }
  }

  if (errors.length || !parsed) {
    return { ok: false, url: "", errors: errors };
  }

  const params = parsed.searchParams;
  for (let i = 0; i < UTM_ORDER.length; i++) params.delete(UTM_ORDER[i]);
  const keys = Object.keys(FIELD_TO_PARAM);
  for (let i = 0; i < keys.length; i++) {
    const field = keys[i];
    if (values[field]) params.append(FIELD_TO_PARAM[field], values[field]);
  }
  return { ok: true, url: parsed.toString(), errors: [] };
}

function inspectUrl(raw) {
  const text = String(raw == null ? "" : raw).trim();
  let parsed;
  try {
    parsed = new URL(text);
  } catch (caught) {
    return [error("url_invalid", "url")];
  }
  if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
    return [error("url_protocol", "url")];
  }

  const rawQuery = parsed.search.startsWith("?") ? parsed.search.slice(1) : parsed.search;
  const keys = [];
  if (rawQuery) {
    const parts = rawQuery.split("&");
    for (let i = 0; i < parts.length; i++) {
      if (!parts[i]) continue;
      const eq = parts[i].indexOf("=");
      const key = decodeURIComponent(eq === -1 ? parts[i] : parts[i].slice(0, eq));
      keys.push(key);
    }
  }

  const findings = [];
  const required = ["utm_source", "utm_medium", "utm_campaign"];
  for (let i = 0; i < required.length; i++) {
    const name = required[i];
    let count = 0;
    for (let k = 0; k < keys.length; k++) if (keys[k] === name) count++;
    if (count === 0) findings.push(error("missing_" + name, name));
    else if (count > 1) findings.push(error("duplicate_" + name, name));
    const value = parsed.searchParams.get(name);
    if (value != null && value.trim() === "") findings.push(error("empty_" + name, name));
    else if (value && !VALUE_RE.test(value)) findings.push(error("utm_value_invalid", name));
  }
  return findings;
}

function extractUrls(text) {
  const re = /https?:\/\/[^\s<>\]\)"'`]+/gi;
  const out = [];
  let match;
  while ((match = re.exec(String(text || ""))) !== null) {
    out.push(match[0].replace(/[.,);:]+$/g, ""));
  }
  return out;
}

function escapeCell(value) {
  return String(value).replace(/\|/g, "\\|").replace(/\r?\n/g, " ");
}

function ledgerHeader() {
  return [
    "| Date | URL | Source | Medium | Campaign | Content | Term |",
    "| --- | --- | --- | --- | --- | --- | --- |",
  ].join("\n");
}

function ledgerRow(input, isoDate) {
  const built = buildUtmUrl(input);
  const source = input || {};
  const cells = [
    isoDate,
    built.ok ? built.url : "",
    String(source.source || "").trim().toLowerCase(),
    String(source.medium || "").trim().toLowerCase(),
    String(source.campaign || "").trim().toLowerCase(),
    String(source.content || "").trim().toLowerCase(),
    String(source.term || "").trim().toLowerCase(),
  ];
  return "| " + cells.map(escapeCell).join(" | ") + " |";
}

function appendLedger(markdown, row) {
  const heading = "## UTM ledger";
  const headerLines = ledgerHeader().split("\n");
  const source = String(markdown || "");
  const lines = source.split("\n");
  const headingAt = lines.findIndex(function (line) {
    return line.trim() === heading;
  });
  if (headingAt === -1) {
    const base = source.replace(/\s*$/, "");
    const prefix = base.length ? base + "\n\n" : "";
    return prefix + heading + "\n\n" + ledgerHeader() + "\n" + row + "\n";
  }
  let cursor = headingAt + 1;
  while (cursor < lines.length && lines[cursor].trim() === "") cursor++;
  if (cursor >= lines.length || lines[cursor].indexOf("| Date |") !== 0) {
    const block = [""].concat(headerLines, [row]);
    lines.splice.apply(lines, [cursor, 0].concat(block));
    return lines.join("\n").replace(/\s*$/, "") + "\n";
  }
  let last = cursor;
  while (last + 1 < lines.length && lines[last + 1].indexOf("|") === 0) last++;
  lines.splice(last + 1, 0, row);
  return lines.join("\n");
}

module.exports = {
  buildUtmUrl: buildUtmUrl,
  inspectUrl: inspectUrl,
  extractUrls: extractUrls,
  ledgerHeader: ledgerHeader,
  ledgerRow: ledgerRow,
  appendLedger: appendLedger,
};
