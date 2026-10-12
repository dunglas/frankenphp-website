#!/usr/bin/env node

import { execFileSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const CONTENT = path.join(ROOT, "content");
const REPO_URL = "https://github.com/php/frankenphp";
const BLOB_URL = `${REPO_URL}/blob/main`;
const RAW_URL = "https://raw.githubusercontent.com/php/frankenphp/main";

const LANGUAGES = [
  { source: "en", locale: "en" },
  { source: "cn", locale: "zh" },
  { source: "es", locale: "es" },
  { source: "fr", locale: "fr" },
  { source: "ja", locale: "ja" },
  { source: "pt-br", locale: "pt-br" },
  { source: "ru", locale: "ru" },
  { source: "tr", locale: "tr" },
];
const LOCALE_CODES = LANGUAGES.map((l) => l.locale).filter((l) => l !== "en");

const GROUPS = [
  { slug: "get-started", pages: ["classic", "worker", "migrate", "config"] },
  {
    slug: "features",
    pages: [
      "early-hints",
      "mercure",
      "hot-reload",
      "logging",
      "observability",
      "metrics",
      "x-sendfile",
      "extensions",
      "extension-workers",
    ],
  },
  {
    slug: "deploy",
    pages: [
      "docker",
      "production",
      "performance",
      "embed",
      "static",
      "compile",
      "github-actions",
    ],
  },
  { slug: "integrations", pages: ["laravel", "symfony", "wordpress", "yii3"] },
  {
    slug: "reference",
    pages: ["known-issues", "library", "security", "internals", "contributing"],
  },
];

const STRINGS = {
  en: {
    intro: "Introduction",
    groups: ["Get started", "Features", "Deploy", "Integrations", "Reference"],
    alerts: { note: "Note", tip: "Tip", important: "Important", warning: "Warning", caution: "Caution" },
  },
  fr: {
    intro: "Introduction",
    groups: ["Premiers pas", "Fonctionnalités", "Déploiement", "Intégrations", "Référence"],
    alerts: { note: "Note", tip: "Astuce", important: "Important", warning: "Avertissement", caution: "Attention" },
  },
  es: {
    intro: "Introducción",
    groups: ["Primeros pasos", "Funcionalidades", "Despliegue", "Integraciones", "Referencia"],
    alerts: { note: "Nota", tip: "Consejo", important: "Importante", warning: "Advertencia", caution: "Precaución" },
  },
  zh: {
    intro: "简介",
    groups: ["入门", "功能", "部署", "集成", "参考"],
    alerts: { note: "注意", tip: "提示", important: "重要", warning: "警告", caution: "小心" },
  },
  ja: {
    intro: "概要",
    groups: ["はじめに", "機能", "デプロイ", "統合", "リファレンス"],
    alerts: { note: "注記", tip: "ヒント", important: "重要", warning: "警告", caution: "注意" },
  },
  "pt-br": {
    intro: "Introdução",
    groups: ["Primeiros passos", "Recursos", "Implantação", "Integrações", "Referência"],
    alerts: { note: "Nota", tip: "Dica", important: "Importante", warning: "Aviso", caution: "Cuidado" },
  },
  ru: {
    intro: "Введение",
    groups: ["Начало работы", "Возможности", "Развёртывание", "Интеграции", "Справочник"],
    alerts: { note: "Примечание", tip: "Совет", important: "Важно", warning: "Предупреждение", caution: "Осторожно" },
  },
  tr: {
    intro: "Giriş",
    groups: ["Başlarken", "Özellikler", "Dağıtım", "Entegrasyonlar", "Başvuru"],
    alerts: { note: "Not", tip: "İpucu", important: "Önemli", warning: "Uyarı", caution: "Dikkat" },
  },
};

const IMAGE_EXT = /\.(png|jpe?g|gif|svg|webp|avif)$/i;
const FENCE = /^\s*(```|~~~)/;

function checkout() {
  if (process.env.FRANKENPHP_REPO) {
    return { dir: path.resolve(process.env.FRANKENPHP_REPO), cleanup: () => {} };
  }
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), "frankenphp-"));
  const token = process.env.GITHUB_KEY;
  const url = token
    ? `https://${token}@github.com/php/frankenphp.git`
    : `${REPO_URL}.git`;
  execFileSync("git", ["clone", "--depth=1", "--quiet", url, dir], {
    stdio: "inherit",
  });
  return { dir, cleanup: () => fs.rmSync(dir, { recursive: true, force: true }) };
}

function splitFrontmatter(source) {
  const match = source.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n?/);
  if (!match) return { meta: {}, body: source };
  const meta = {};
  for (const key of ["title", "description"]) {
    const line = match[1].match(new RegExp(`^${key}:\\s*(.+?)\\s*$`, "m"));
    if (line) meta[key] = line[1].replace(/^(["'])(.*)\1$/, "$2");
  }
  return { meta, body: source.slice(match[0].length) };
}

function plainText(markdown) {
  return markdown
    .replace(/!\[[^\]]*\]\([^)]*\)/g, "")
    .replace(/\[([^\]]+)\]\([^)]*\)/g, "$1")
    .replace(/<[^>]+>/g, "")
    .replace(/[*_`]/g, "")
    .replace(/\s+/g, " ")
    .trim();
}

function truncate(text, max = 160) {
  if (text.length <= max) return text;
  const cut = text.slice(0, max - 1);
  return `${cut.slice(0, cut.lastIndexOf(" ")).replace(/[,;:.]$/, "")}…`;
}

function mapProse(body, fn) {
  let fenced = false;
  return body
    .split("\n")
    .map((line) => {
      if (FENCE.test(line)) {
        fenced = !fenced;
        return line;
      }
      return fenced ? line : fn(line);
    })
    .join("\n");
}

function mapOutsideInlineCode(line, fn) {
  return line
    .split(/(`+[^`]*?`+)/)
    .map((part, i) => (i % 2 ? part : fn(part)))
    .join("");
}

function extractTitle(body) {
  let fenced = false;
  const lines = body.split("\n");
  for (let i = 0; i < lines.length; i++) {
    if (FENCE.test(lines[i])) fenced = !fenced;
    const match = !fenced && lines[i].match(/^#\s+(.+?)\s*#*\s*$/);
    if (match) {
      lines.splice(i, 1);
      if (lines[i] === "") lines.splice(i, 1);
      return { title: plainText(match[1]), body: lines.join("\n") };
    }
  }
  return { title: "", body };
}

function firstParagraph(body) {
  const lines = body.split("\n");
  let fenced = false;
  let start = -1;
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (FENCE.test(line)) {
      if (start !== -1) return { start, end: i, raw: lines.slice(start, i).join(" ") };
      fenced = !fenced;
      continue;
    }
    if (fenced) continue;
    const prose = line.trim() && !/^\s*([#>|<!-]|\d+\.|\*\s)/.test(line);
    if (prose && start === -1) start = i;
    if (!prose && start !== -1) return { start, end: i, raw: lines.slice(start, i).join(" ") };
  }
  return start === -1 ? null : { start, end: lines.length, raw: lines.slice(start).join(" ") };
}

function describe(body) {
  const paragraph = firstParagraph(body);
  if (!paragraph) return { body };
  const text = plainText(paragraph.raw);
  if (/[`*_<>\[\]]/.test(paragraph.raw)) return { body, seoDescription: truncate(text) };
  const lines = body.split("\n");
  lines.splice(paragraph.start, paragraph.end - paragraph.start);
  return {
    body: lines.join("\n"),
    description: text,
    seoDescription: text.length > 160 ? truncate(text) : undefined,
  };
}

function convertAlerts(body, labels) {
  const lines = body.split("\n");
  const out = [];
  let fenced = false;
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (FENCE.test(line)) fenced = !fenced;
    const match = !fenced && line.match(/^(\s*)>\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*(.*)$/i);
    if (!match) {
      out.push(line);
      continue;
    }
    const [, indent, rawType, rest] = match;
    const type = rawType.toLowerCase();
    const inner = rest ? [rest] : [];
    while (i + 1 < lines.length && lines[i + 1].startsWith(`${indent}>`)) {
      i++;
      inner.push(lines[i].slice(indent.length + 1).replace(/^ /, ""));
    }
    while (inner.length && !inner[0].trim()) inner.shift();
    while (inner.length && !inner.at(-1).trim()) inner.pop();
    out.push(
      `${indent}<div class="fp-callout" data-callout="${type}">`,
      `${indent}<p class="fp-callout-title">${labels[type]}</p>`,
      "",
      ...inner.map((l) => (l ? indent + l : "")),
      "",
      `${indent}</div>`,
    );
  }
  return out.join("\n");
}

function docRoute(target) {
  const [rawPath, hash = ""] = target.split("#");
  const anchor = hash ? `#${hash}` : "";
  const absolute = /^https?:\/\/frankenphp\.dev/.test(rawPath);
  const clean = rawPath.replace(/^https?:\/\/frankenphp\.dev/, "");
  const locales = [...LOCALE_CODES, "cn"].join("|");
  if (clean === "" || clean === "./") return absolute ? `/${anchor}` : anchor || null;
  if (new RegExp(`^/(?:(?:${locales})/?)?$`).test(clean)) return `/${anchor}`;
  if (/(^|\/)README\.md$/i.test(clean) || /^(\.\.\/)+$/.test(clean)) {
    return `/docs${anchor}`;
  }
  if (/(^|\/)CONTRIBUTING\.md$/i.test(clean)) return `/docs/contributing${anchor}`;
  const md = clean.match(/(?:^|\/)([\w-]+)\.md$/);
  if (md) return `/docs/${md[1].toLowerCase()}${anchor}`;
  const site = clean.match(
    new RegExp(`^/(?:(?:${locales})/)?docs(?:/(?:${locales}))?(?:/([\\w-]+))?/?$`),
  );
  if (site) return `/docs${site[1] ? `/${site[1]}` : ""}${anchor}`;
  return undefined;
}

function rewriteLinks(body, { repoPath, fileDir, assetsDir }) {
  const resolveRepo = (target) =>
    path.posix.normalize(path.posix.join(path.posix.dirname(repoPath), target));

  const fix = (target) => {
    if (/^(mailto:|tel:|#)/.test(target)) return target;
    if (/^https?:\/\//.test(target) && !/^https?:\/\/frankenphp\.dev/.test(target)) {
      return target;
    }
    const route = docRoute(target);
    if (route) return route;
    if (/^https?:\/\//.test(target)) return target;
    if (IMAGE_EXT.test(target.split("#")[0])) {
      const name = path.posix.basename(target);
      return path.relative(fileDir, path.join(assetsDir, name)).split(path.sep).join("/");
    }
    if (target.startsWith("/")) return target;
    return `${BLOB_URL}/${resolveRepo(target)}`;
  };

  return mapProse(body, (line) =>
    mapOutsideInlineCode(line, (text) =>
      text
        .replace(/\]\(\s*<?([^)\s>]+)>?(\s+"[^"]*")?\s*\)/g, (_, target, title = "") => `](${fix(target)}${title})`)
        .replace(/src="((?!https?:)[^"]+)"/g, (_, src) => `src="${RAW_URL}/${resolveRepo(src)}"`),
    ),
  );
}

function navLabels(readme) {
  const sections = [...readme.matchAll(/^##\s.*$/gm)];
  const labels = {};
  if (sections.length < 2) return labels;
  const start = sections[1].index;
  const end = sections[2]?.index ?? readme.length;
  for (const [, text, url] of readme.slice(start, end).matchAll(/\[([^\]]+)\]\(([^)]+)\)/g)) {
    const route = docRoute(url.trim());
    const slug = route?.match(/^\/docs\/([\w-]+)$/)?.[1];
    if (slug && !labels[slug]) labels[slug] = plainText(text);
  }
  return labels;
}

const yaml = (value) => JSON.stringify(value);

function frontmatter({ title, description, seoTitle, seoDescription, label, order }) {
  const lines = ["---", `title: ${yaml(title)}`];
  if (description) lines.push(`description: ${yaml(description)}`);
  if (label || order !== undefined) {
    lines.push("sidebar:");
    if (label) lines.push(`  label: ${yaml(label)}`);
    if (order !== undefined) lines.push(`  order: ${order}`);
  }
  const seo = [];
  if (seoTitle && seoTitle !== title) seo.push(`  title: ${yaml(seoTitle)}`);
  if (seoDescription) seo.push(`  description: ${yaml(seoDescription)}`);
  if (seo.length) lines.push("seo:", ...seo);
  lines.push("---", "");
  return lines.join("\n");
}

function groupOf(slug) {
  const index = GROUPS.findIndex((g) => g.pages.includes(slug));
  return index === -1 ? GROUPS.length - 1 : index;
}

function groupFolder(index) {
  return `(${String(index + 1).padStart(2, "0")}-${GROUPS[index].slug})`;
}

function writeLanguage(repo, { source, locale }) {
  const strings = STRINGS[locale];
  const docsSource = source === "en" ? path.join(repo, "docs") : path.join(repo, "docs", source);
  const docsDest = locale === "en" ? path.join(CONTENT, "docs") : path.join(CONTENT, locale, "docs");
  const assetsDir = path.join(CONTENT, "assets");
  if (!fs.existsSync(docsSource)) {
    console.warn(`Skipping ${locale}: ${docsSource} not found`);
    return 0;
  }

  const readmePath = fs.existsSync(path.join(docsSource, "README.md"))
    ? path.join(docsSource, "README.md")
    : path.join(repo, "README.md");
  const readme = fs.readFileSync(readmePath, "utf8");
  const labels = navLabels(readme);

  const pages = fs
    .readdirSync(docsSource)
    .filter((f) => f.endsWith(".md") && !/^(README|CONTRIBUTING)\.md$/i.test(f))
    .map((f) => ({ slug: f.slice(0, -3).toLowerCase(), file: path.join(docsSource, f) }));
  const contributing = source === "en"
    ? path.join(repo, "CONTRIBUTING.md")
    : path.join(docsSource, "CONTRIBUTING.md");
  if (fs.existsSync(contributing)) pages.push({ slug: "contributing", file: contributing });

  const write = (dest, sourceFile, { label, order } = {}) => {
    const raw = fs.readFileSync(sourceFile, "utf8").replace(/\r\n/g, "\n");
    const { meta, body: withTitle } = splitFrontmatter(raw);
    let { title, body } = extractTitle(withTitle);
    body = body.replace(/^<h1 align="center">.*<\/h1>\s*\n/m, "");
    body = convertAlerts(body, strings.alerts);
    body = rewriteLinks(body, {
      repoPath: path.relative(repo, sourceFile).split(path.sep).join("/"),
      fileDir: path.dirname(dest),
      assetsDir,
    });
    title ||= meta.title || "FrankenPHP";
    let description = meta.description;
    let seoDescription;
    if (!description) ({ body, description, seoDescription } = describe(body));
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.writeFileSync(
      dest,
      frontmatter({ title, description, seoTitle: meta.title, seoDescription, label, order }) +
        body.trimStart(),
    );
  };

  write(path.join(docsDest, "index.md"), readmePath, { label: strings.intro });

  for (const { slug, file } of pages) {
    const group = groupOf(slug);
    const order = GROUPS[group].pages.indexOf(slug);
    write(path.join(docsDest, groupFolder(group), `${slug}.md`), file, {
      label: labels[slug],
      order: order === -1 ? 100 : order,
    });
  }

  GROUPS.forEach((group, index) => {
    const dir = path.join(docsDest, groupFolder(index));
    if (!fs.existsSync(dir)) return;
    fs.writeFileSync(
      path.join(dir, "meta.ts"),
      `import { defineMeta } from "blume";\n\nexport default defineMeta({\n  title: ${yaml(strings.groups[index])},\n});\n`,
    );
  });

  return pages.length + 1;
}

const { dir: repo, cleanup } = checkout();
try {
  fs.rmSync(CONTENT, { recursive: true, force: true });
  fs.mkdirSync(path.join(CONTENT, "assets"), { recursive: true });
  for (const image of fs.readdirSync(path.join(repo, "docs")).filter((f) => IMAGE_EXT.test(f))) {
    fs.copyFileSync(path.join(repo, "docs", image), path.join(CONTENT, "assets", image));
  }

  for (const language of LANGUAGES) {
    const count = writeLanguage(repo, language);
    console.log(`✓ ${language.locale}: ${count} pages`);
  }

  for (const installer of ["install.sh", "install.ps1"]) {
    fs.copyFileSync(path.join(repo, installer), path.join(ROOT, "public", installer));
  }
} finally {
  cleanup();
}
