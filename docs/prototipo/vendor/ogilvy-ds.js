/* @ds-bundle: {"format":4,"namespace":"OgilvyDesignSystem_bbfe64","components":[{"name":"KickerTitle","sourcePath":"components/brand/KickerTitle.jsx"},{"name":"Logo","sourcePath":"components/brand/Logo.jsx"},{"name":"NumberedItem","sourcePath":"components/brand/NumberedItem.jsx"},{"name":"Shape","sourcePath":"components/brand/Shape.jsx"},{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"Card","sourcePath":"components/core/Card.jsx"},{"name":"Tag","sourcePath":"components/core/Tag.jsx"}],"sourceHashes":{"components/brand/KickerTitle.jsx":"49c323471170","components/brand/Logo.jsx":"e2a3946cbc8b","components/brand/NumberedItem.jsx":"90ef6d859568","components/brand/Shape.jsx":"abb851385a71","components/core/Button.jsx":"c16d549081e4","components/core/Card.jsx":"8058d4f63fc9","components/core/Tag.jsx":"457cf349ddc8"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.OgilvyDesignSystem_bbfe64 = window.OgilvyDesignSystem_bbfe64 || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/brand/KickerTitle.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Kicker + title lockup — the core editorial heading pattern.
 * Red kicker (optionally with a two-digit number) over a headline.
 * font: "serif" for cover/close headlines, "sans" for section titles.
 */
function KickerTitle({
  kicker,
  number,
  title,
  font = "sans",
  size = 40,
  align = "left",
  inverse = false,
  style = {},
  ...rest
}) {
  const fs = typeof size === "number" ? size : parseFloat(size) || 40;
  const titleColor = inverse ? "#fff" : "var(--og-black)";
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      textAlign: align,
      ...style
    }
  }, rest), (kicker || number) && /*#__PURE__*/React.createElement("div", {
    style: {
      font: "700 13px/1 var(--font-sans)",
      textTransform: "uppercase",
      letterSpacing: "0.14em",
      color: "var(--og-red)",
      marginBottom: 14
    }
  }, number != null && /*#__PURE__*/React.createElement("span", {
    style: {
      marginRight: 10
    }
  }, String(number).padStart(2, "0")), kicker), /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: font === "serif" ? "var(--font-serif)" : "var(--font-sans)",
      fontWeight: font === "serif" ? 300 : 700,
      fontSize: fs,
      lineHeight: font === "serif" ? 1.02 : 1.12,
      letterSpacing: "-0.02em",
      color: titleColor,
      textWrap: "balance"
    }
  }, title));
}
Object.assign(__ds_scope, { KickerTitle });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/brand/KickerTitle.jsx", error: String((e && e.message) || e) }); }

// components/brand/Logo.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const SRC = {
  red: "../../assets/logo-ogilvy-red.png",
  white: "../../assets/logo-ogilvy-white.png",
  black: "../../assets/logo-ogilvy-black.png"
};

/**
 * Ogilvy wordmark. The mark is always the serif lockup image — never re-typed.
 * variant: red | white | black. Provide `src` to override the asset path
 * (paths default to a component-dir-relative location).
 */
function Logo({
  variant = "red",
  height = 40,
  src,
  alt = "Ogilvy",
  style = {},
  ...rest
}) {
  const h = typeof height === "number" ? `${height}px` : /^\d+(\.\d+)?$/.test(String(height)) ? `${height}px` : height;
  return /*#__PURE__*/React.createElement("img", _extends({
    src: src || SRC[variant] || SRC.red,
    alt: alt,
    style: {
      height: h,
      width: "auto",
      display: "block",
      ...style
    }
  }, rest));
}
Object.assign(__ds_scope, { Logo });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/brand/Logo.jsx", error: String((e && e.message) || e) }); }

// components/brand/NumberedItem.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Numbered agenda / list item. Giant red two-digit number beside a title + optional body.
 * Used for agendas, process steps, timelines.
 */
function NumberedItem({
  number,
  title,
  children,
  inverse = false,
  style = {},
  ...rest
}) {
  const fg = inverse ? "#fff" : "var(--og-black)";
  const muted = inverse ? "rgba(255,255,255,.7)" : "var(--og-grey-700)";
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      display: "flex",
      gap: "var(--sp-5)",
      alignItems: "baseline",
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      font: "700 clamp(28px,6vw,48px)/0.9 var(--font-sans)",
      color: "var(--og-red)",
      letterSpacing: "-0.03em",
      flex: "none",
      minWidth: "1.6em"
    }
  }, String(number).padStart(2, "0")), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      font: "700 20px/1.2 var(--font-sans)",
      color: fg
    }
  }, title), children && /*#__PURE__*/React.createElement("div", {
    style: {
      font: "400 15px/1.5 var(--font-sans)",
      color: muted,
      marginTop: 6,
      maxWidth: "52ch"
    }
  }, children)));
}
Object.assign(__ds_scope, { NumberedItem });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/brand/NumberedItem.jsx", error: String((e && e.message) || e) }); }

// components/brand/Shape.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Ogilvy geometric graphic primitive — the modular shape kit.
 * type: half | quarter | circle | ring | square | hatch | cube
 * color: red | black | grey | white  (ignored by cube, which is neutral 3D)
 * rotate: degrees (for half/quarter orientation).
 */
function Shape({
  type = "half",
  color = "red",
  size = 96,
  rotate = 0,
  style = {},
  ...rest
}) {
  size = typeof size === "number" ? size : parseFloat(size) || 96;
  rotate = typeof rotate === "number" ? rotate : parseFloat(rotate) || 0;
  const fill = {
    red: "var(--og-red)",
    black: "var(--og-black)",
    grey: "var(--og-grey-500)",
    white: "var(--og-white)"
  }[color] || "var(--og-red)";
  const box = {
    width: size,
    height: size,
    transform: rotate ? `rotate(${rotate}deg)` : undefined
  };
  if (type === "cube") {
    const f = size,
      h = f / 2;
    return /*#__PURE__*/React.createElement("div", _extends({
      style: {
        position: "relative",
        width: f,
        height: f,
        ...style
      }
    }, rest), /*#__PURE__*/React.createElement("i", {
      style: {
        position: "absolute",
        width: h,
        height: h,
        background: "var(--og-grey-150)",
        transform: "skewX(-45deg)",
        left: h / 2,
        top: 0
      }
    }), /*#__PURE__*/React.createElement("i", {
      style: {
        position: "absolute",
        width: h,
        height: h,
        background: "var(--og-grey-500)",
        transform: "skewY(-45deg)",
        left: 0,
        top: h / 2
      }
    }), /*#__PURE__*/React.createElement("i", {
      style: {
        position: "absolute",
        width: h,
        height: h,
        background: "var(--og-black)",
        transform: "skewY(45deg)",
        left: h,
        top: h / 2
      }
    }));
  }
  const shapeStyle = {
    ...box,
    ...style
  };
  if (type === "half") Object.assign(shapeStyle, {
    background: fill,
    borderRadius: `${size}px ${size}px 0 0`
  });else if (type === "quarter") Object.assign(shapeStyle, {
    background: fill,
    borderRadius: `${size}px 0 0 0`
  });else if (type === "circle") Object.assign(shapeStyle, {
    background: fill,
    borderRadius: "50%"
  });else if (type === "ring") Object.assign(shapeStyle, {
    borderRadius: "50%",
    boxShadow: `inset 0 0 0 ${Math.max(6, size * 0.12)}px ${fill}`
  });else if (type === "square") Object.assign(shapeStyle, {
    background: fill
  });else if (type === "hatch") Object.assign(shapeStyle, {
    background: `repeating-linear-gradient(45deg, ${fill} 0 ${Math.max(3, size * 0.05)}px, transparent ${Math.max(3, size * 0.05)}px ${Math.max(8, size * 0.12)}px)`
  });
  return /*#__PURE__*/React.createElement("div", _extends({
    style: shapeStyle
  }, rest));
}
Object.assign(__ds_scope, { Shape });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/brand/Shape.jsx", error: String((e && e.message) || e) }); }

// components/core/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Ogilvy Button — crisp, geometric, hard-edged.
 * Variants: primary (red), dark (black), ghost (outline). Sizes: sm | md | lg.
 */
function Button({
  children,
  variant = "primary",
  size = "md",
  disabled = false,
  iconLeft = null,
  iconRight = null,
  onClick,
  type = "button",
  ...rest
}) {
  const pad = {
    sm: "8px 16px",
    md: "12px 22px",
    lg: "16px 30px"
  }[size] || "12px 22px";
  const fs = {
    sm: 13,
    md: 15,
    lg: 17
  }[size] || 15;
  const palette = {
    primary: {
      bg: "var(--og-red)",
      fg: "#fff",
      bd: "var(--og-red)",
      hbg: "var(--og-red-ink)"
    },
    dark: {
      bg: "var(--og-black)",
      fg: "#fff",
      bd: "var(--og-black)",
      hbg: "var(--og-grey-900)"
    },
    ghost: {
      bg: "transparent",
      fg: "var(--og-black)",
      bd: "var(--og-black)",
      hbg: "var(--og-grey-050)"
    }
  }[variant] || {};
  const [hover, setHover] = React.useState(false);
  const [active, setActive] = React.useState(false);
  return /*#__PURE__*/React.createElement("button", _extends({
    type: type,
    disabled: disabled,
    onClick: onClick,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => {
      setHover(false);
      setActive(false);
    },
    onMouseDown: () => setActive(true),
    onMouseUp: () => setActive(false),
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 10,
      font: `700 ${fs}px/1 var(--font-sans)`,
      letterSpacing: "0.01em",
      padding: pad,
      color: palette.fg,
      background: disabled ? "var(--og-grey-300)" : hover ? palette.hbg : palette.bg,
      border: `2px solid ${disabled ? "var(--og-grey-300)" : palette.bd}`,
      borderRadius: "var(--radius-none)",
      cursor: disabled ? "not-allowed" : "pointer",
      transform: active && !disabled ? "translateY(1px)" : "none",
      transition: "background var(--dur-fast) var(--ease-standard), transform var(--dur-fast)",
      outline: "none"
    }
  }, rest), iconLeft, /*#__PURE__*/React.createElement("span", null, children), iconRight);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Button.jsx", error: String((e && e.message) || e) }); }

// components/core/Card.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Ogilvy Card — flat editorial surface with a hairline rule.
 * variant: plain (white + hairline) | print (hard 2px black offset) | ink (black) | brand (red).
 * accentBar draws a 4px red rule on the top edge.
 */
function Card({
  children,
  variant = "plain",
  accentBar = false,
  style = {},
  ...rest
}) {
  const map = {
    plain: {
      bg: "#fff",
      fg: "var(--og-black)",
      bd: "1px solid var(--og-grey-150)",
      sh: "none"
    },
    print: {
      bg: "#fff",
      fg: "var(--og-black)",
      bd: "2px solid var(--og-black)",
      sh: "var(--shadow-card)"
    },
    ink: {
      bg: "var(--og-black)",
      fg: "#fff",
      bd: "none",
      sh: "none"
    },
    brand: {
      bg: "var(--og-red)",
      fg: "#fff",
      bd: "none",
      sh: "none"
    }
  }[variant] || {};
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      position: "relative",
      background: map.bg,
      color: map.fg,
      border: map.bd,
      boxShadow: map.sh,
      borderRadius: "var(--radius-none)",
      padding: "var(--sp-6)",
      ...style
    }
  }, rest), accentBar && /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      left: 0,
      top: 0,
      height: 4,
      width: "100%",
      background: "var(--og-red)"
    }
  }), children);
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Card.jsx", error: String((e && e.message) || e) }); }

// components/core/Tag.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Ogilvy Tag / pill label. Small categorical marker.
 * tone: red | dark | grey | outline.
 */
function Tag({
  children,
  tone = "red",
  ...rest
}) {
  const map = {
    red: {
      bg: "var(--og-red)",
      fg: "#fff",
      bd: "transparent"
    },
    dark: {
      bg: "var(--og-black)",
      fg: "#fff",
      bd: "transparent"
    },
    grey: {
      bg: "var(--og-grey-150)",
      fg: "var(--og-black)",
      bd: "transparent"
    },
    outline: {
      bg: "transparent",
      fg: "var(--og-black)",
      bd: "var(--og-black)"
    }
  }[tone] || {};
  return /*#__PURE__*/React.createElement("span", _extends({
    style: {
      display: "inline-flex",
      alignItems: "center",
      font: "700 12px/1 var(--font-sans)",
      textTransform: "uppercase",
      letterSpacing: "0.12em",
      padding: "6px 12px",
      color: map.fg,
      background: map.bg,
      border: `1px solid ${map.bd}`,
      borderRadius: "var(--radius-pill)"
    }
  }, rest), children);
}
Object.assign(__ds_scope, { Tag });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Tag.jsx", error: String((e && e.message) || e) }); }

__ds_ns.KickerTitle = __ds_scope.KickerTitle;

__ds_ns.Logo = __ds_scope.Logo;

__ds_ns.NumberedItem = __ds_scope.NumberedItem;

__ds_ns.Shape = __ds_scope.Shape;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.Tag = __ds_scope.Tag;

})();
