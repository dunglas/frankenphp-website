type CodeThemes = { light: object; dark: object };

// The Shiki theme for every code block, Markdown fences and homepage panels
// alike, drawn from the mascot's palette.

const palette = (p: {
  name: string;
  type: "light" | "dark";
  bg: string;
  fg: string;
  comment: string;
  keyword: string;
  string: string;
  number: string;
  fn: string;
}) => ({
  name: p.name,
  type: p.type,
  colors: { "editor.background": p.bg, "editor.foreground": p.fg },
  settings: [
    { settings: { foreground: p.fg, background: p.bg } },
    { scope: ["comment", "punctuation.definition.comment"], settings: { foreground: p.comment } },
    {
      scope: [
        "keyword",
        "storage",
        "storage.type",
        "storage.modifier",
        "constant.language",
        "support.type.primitive",
        "keyword.control",
        "keyword.operator.new",
        "entity.name.tag",
      ],
      settings: { foreground: p.keyword },
    },
    { scope: ["string", "string.quoted", "punctuation.definition.string"], settings: { foreground: p.string } },
    { scope: ["constant.numeric", "constant.character"], settings: { foreground: p.number } },
    {
      scope: ["entity.name.function", "support.function", "meta.function-call", "entity.name.type", "support.class"],
      settings: { foreground: p.fn },
    },
    { scope: ["variable", "variable.other", "variable.parameter"], settings: { foreground: p.fg } },
  ],
});

// Code is always set on the night-purple ground of the illustrations, in light
// and dark mode alike: lilac keywords, lime strings, soft violet comments.
const night = palette({
  name: "frankenphp-night",
  type: "dark",
  bg: "#1a0330",
  fg: "#f4eefb",
  comment: "#9c86b5",
  keyword: "#c3b2d3",
  string: "#b3d133",
  number: "#b3d133",
  fn: "#ffffff",
});

export const codeThemes: CodeThemes = { light: night, dark: night };
