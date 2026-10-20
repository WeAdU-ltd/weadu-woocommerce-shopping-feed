"use strict";

const LIMITS = { headline: 30, description: 90, path: 15 };

function charWidth(ch) {
  const cp = ch.codePointAt(0) || 0;
  if (
    (cp >= 0x1100 && cp <= 0x115f) ||
    (cp >= 0x2e80 && cp <= 0x9fff) ||
    (cp >= 0xac00 && cp <= 0xd7a3) ||
    (cp >= 0xf900 && cp <= 0xfaff) ||
    (cp >= 0xfe10 && cp <= 0xfe19) ||
    (cp >= 0xfe30 && cp <= 0xfe6f) ||
    (cp >= 0xff00 && cp <= 0xff60) ||
    (cp >= 0xffe0 && cp <= 0xffe6)
  ) {
    return 2;
  }
  return 1;
}

function googleAdsWidth(text) {
  const chars = Array.from(String(text || ""));
  let width = 0;
  for (let i = 0; i < chars.length; i++) width += charWidth(chars[i]);
  return width;
}

function resolveRole(line, role) {
  if (role === "headline" || role === "description" || role === "path") return role;
  const width = googleAdsWidth(line);
  if (width <= LIMITS.headline) return "headline";
  return "description";
}

function lintAdLine(line, role) {
  const text = String(line == null ? "" : line).trim();
  const resolved = resolveRole(text, role || "auto");
  const limit = LIMITS[resolved];
  const codes = [];
  if (!text) {
    codes.push("empty_line");
    return { role: resolved, text: "", width: 0, limit: limit, codes: codes };
  }
  const width = googleAdsWidth(text);
  if (width > limit) codes.push("over_limit");
  if (resolved === "headline" && text.indexOf("!") !== -1) codes.push("headline_exclamation");
  if (/!{2,}|\?{2,}|\.{3,}/.test(text)) codes.push("repeated_punctuation");
  if (/(?:\+?\d[\d\s().-]{7,}\d)/.test(text)) codes.push("phone_number");
  const words = text.split(/\s+/);
  for (let i = 0; i < words.length; i++) {
    const letters = words[i].replace(/[^A-Za-z]/g, "");
    if (letters.length >= 4 && letters === letters.toUpperCase()) {
      codes.push("all_caps_word");
      break;
    }
  }
  if (/\b(best|#1|number one|n°1|no\. 1)\b/i.test(text)) codes.push("superlative");
  return { role: resolved, text: text, width: width, limit: limit, codes: codes };
}

function cleanPhrase(value) {
  return String(value == null ? "" : value).trim().replace(/\s+/g, " ");
}

function fit(text, limit) {
  const chars = Array.from(text);
  let width = 0;
  let end = 0;
  for (let i = 0; i < chars.length; i++) {
    const next = charWidth(chars[i]);
    if (width + next > limit) break;
    width += next;
    end = i + 1;
  }
  if (end === chars.length) {
    return { text: chars.join(""), width: width, trimmed: false, midWord: false };
  }
  const kept = chars.slice(0, end).join("");
  const space = kept.lastIndexOf(" ");
  if (space > 0) {
    const cut = kept.slice(0, space).trim();
    return { text: cut, width: googleAdsWidth(cut), trimmed: true, midWord: false };
  }
  const hard = kept.trim();
  return { text: hard, width: googleAdsWidth(hard), trimmed: true, midWord: true };
}

function pushCandidate(out, seen, role, raw) {
  const phrase = cleanPhrase(raw);
  if (!phrase) return;
  const limit = LIMITS[role];
  const fitted = fit(phrase, limit);
  if (!fitted.text) return;
  const key = role + "\n" + fitted.text;
  if (seen[key]) return;
  seen[key] = true;
  const lint = lintAdLine(fitted.text, role);
  const codes = [];
  for (let i = 0; i < lint.codes.length; i++) {
    if (lint.codes[i] !== "over_limit" && lint.codes[i] !== "empty_line") codes.push(lint.codes[i]);
  }
  if (fitted.trimmed) codes.push(fitted.midWord ? "cut_mid_word" : "trimmed");
  out.push({
    role: role,
    text: fitted.text,
    width: lint.width,
    limit: limit,
    codes: codes,
  });
}

function buildRsaCandidates(input) {
  const source = input || {};
  const product = cleanPhrase(source.product);
  const benefit = cleanPhrase(source.benefit);
  const offer = cleanPhrase(source.offer);
  const cta = cleanPhrase(source.cta);
  const out = [];
  const seen = {};
  pushCandidate(out, seen, "headline", product);
  if (benefit) {
    pushCandidate(out, seen, googleAdsWidth(benefit) <= LIMITS.headline ? "headline" : "description", benefit);
  }
  pushCandidate(out, seen, "headline", offer);
  pushCandidate(out, seen, "headline", cta);
  if (product && cta) pushCandidate(out, seen, "headline", cta + " " + product);
  if (product && offer) pushCandidate(out, seen, "headline", product + " " + offer);
  if (benefit && offer && googleAdsWidth(benefit) > LIMITS.headline) {
    pushCandidate(out, seen, "description", benefit + " " + offer);
  }
  return out;
}

module.exports = {
  LIMITS: LIMITS,
  googleAdsWidth: googleAdsWidth,
  lintAdLine: lintAdLine,
  buildRsaCandidates: buildRsaCandidates,
};
