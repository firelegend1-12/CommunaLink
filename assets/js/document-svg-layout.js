(function(global) {
    const rafByRoot = new WeakMap();

    function normalizeText(value) {
        return String(value ?? "").replace(/\s+/g, " ").trim();
    }

    function getOriginalTextElements(root) {
        return Array.from(root.querySelectorAll("svg text")).filter(function(el) {
            return el.dataset.svgPlaceholder !== "1";
        });
    }

    function getTextElement(root, id) {
        if (!root || !id) {
            return null;
        }
        return root.getElementById ? root.getElementById(id) : document.getElementById(id);
    }

    function getPrimaryTspan(el) {
        return el ? el.querySelector("tspan") : null;
    }

    function parseXCoords(xAttr) {
        return String(xAttr || "0")
            .split(/\s+/)
            .map(parseFloat)
            .filter(function(n) { return !isNaN(n); });
    }

    function getFirstX(tspan) {
        const coords = parseXCoords(tspan.dataset.origX || tspan.getAttribute("x") || "0");
        return coords.length > 0 ? coords[0] : 0;
    }

    function getFirstY(tspan) {
        const value = parseFloat(tspan.dataset.origY || tspan.getAttribute("y") || "0");
        return isNaN(value) ? 0 : value;
    }

    function measureTextLength(el) {
        try {
            return el.getComputedTextLength();
        } catch (error) {
            return 0;
        }
    }

    function getOriginalSpan(tspan, fallbackWidth) {
        const coords = parseXCoords(tspan.dataset.origX || tspan.getAttribute("x") || "0");
        const storedWidth = parseFloat(tspan.dataset.origTextLength || "");
        const hasStoredWidth = !isNaN(storedWidth);
        const widthFallback = hasStoredWidth
            ? Math.max(storedWidth, 0)
            : (fallbackWidth > 0 ? fallbackWidth : 0);

        if (coords.length === 0) {
            return {
                start: 0,
                end: widthFallback,
                width: widthFallback
            };
        }

        const start = coords[0];
        if (hasStoredWidth) {
            return {
                start: start,
                end: start + widthFallback,
                width: widthFallback
            };
        }

        if (coords.length === 1) {
            return {
                start: start,
                end: start + widthFallback,
                width: widthFallback
            };
        }

        const advances = [];
        for (let i = 1; i < coords.length; i += 1) {
            const advance = coords[i] - coords[i - 1];
            if (advance > 0) {
                advances.push(advance);
            }
        }

        const avgAdvance = advances.length > 0
            ? advances.reduce(function(sum, value) { return sum + value; }, 0) / advances.length
            : widthFallback;
        const end = coords[coords.length - 1] + avgAdvance;

        return {
            start: start,
            end: end,
            width: Math.max(end - start, widthFallback)
        };
    }

    function parsePlaceholderText(text) {
        const match = String(text || "").match(/^([^_]*?)(_+)(.*)$/);
        if (!match) {
            return {
                hasUnderline: false,
                prefix: "",
                underline: "",
                suffix: ""
            };
        }

        return {
            hasUnderline: true,
            prefix: match[1],
            underline: match[2],
            suffix: match[3]
        };
    }

    function rememberOriginalState(root) {
        getOriginalTextElements(root).forEach(function(el) {
            if (!el.dataset.origTransform && el.hasAttribute("transform")) {
                el.dataset.origTransform = el.getAttribute("transform");
            }
            if (!el.dataset.origDisplay) {
                el.dataset.origDisplay = el.style.display || "";
            }

            const tspan = getPrimaryTspan(el);
            if (!tspan) {
                return;
            }

            if (!tspan.dataset.origText) {
                tspan.dataset.origText = tspan.textContent;
            }
            if (!tspan.dataset.origX) {
                tspan.dataset.origX = tspan.getAttribute("x") || "0";
            }
            if (!tspan.dataset.origY) {
                tspan.dataset.origY = tspan.getAttribute("y") || "0";
            }
            if (!tspan.dataset.origTextDecoration) {
                tspan.dataset.origTextDecoration = tspan.style.textDecoration || "";
            }
            if (!tspan.dataset.origTextLength) {
                tspan.dataset.origTextLength = String(measureTextLength(el));
            }
        });
    }

    function resetPlaceholderClones(root) {
        Array.from(root.querySelectorAll("svg text[data-svg-placeholder='1']")).forEach(function(el) {
            el.style.display = "none";
            const tspan = getPrimaryTspan(el);
            if (tspan) {
                tspan.textContent = "";
            }
        });
    }

    function resetOriginalLayout(root) {
        rememberOriginalState(root);
        resetPlaceholderClones(root);

        getOriginalTextElements(root).forEach(function(el) {
            el.style.display = el.dataset.origDisplay || "";
            delete el.dataset.svgGroupEndX;
            if (el.dataset.origTransform) {
                el.setAttribute("transform", el.dataset.origTransform);
            }

            const tspan = getPrimaryTspan(el);
            if (!tspan) {
                return;
            }

            tspan.textContent = tspan.dataset.origText || "";
            tspan.setAttribute("x", tspan.dataset.origX || "0");
            tspan.setAttribute("y", tspan.dataset.origY || "0");
            tspan.style.textDecoration = tspan.dataset.origTextDecoration || "";
            tspan.style.textUnderlineOffset = "";
        });
    }

    function getPlaceholderClone(root, sourceEl, fieldId) {
        let clone = getTextElement(root, fieldId + "-placeholder");
        if (clone) {
            return clone;
        }

        clone = sourceEl.cloneNode(true);
        clone.id = fieldId + "-placeholder";
        clone.dataset.svgPlaceholder = "1";
        clone.removeAttribute("data-orig-transform");
        sourceEl.parentNode.insertBefore(clone, sourceEl);
        return clone;
    }

    function buildUnderlineXAttr(origX, prefixLength, underlineLength) {
        const coords = String(origX || "0").split(/\s+/).filter(function(part) {
            return part !== "";
        });

        if (coords.length === 0) {
            return "0";
        }

        const slice = coords.slice(prefixLength, prefixLength + underlineLength);
        return (slice.length > 0 ? slice : [coords[Math.min(prefixLength, coords.length - 1)]]).join(" ");
    }

    function applyFieldValue(root, fieldId, value, groupedIds) {
        const el = getTextElement(root, fieldId);
        const tspan = getPrimaryTspan(el);
        if (!el || !tspan) {
            return;
        }

        const originalText = tspan.dataset.origText || "";
        const placeholder = parsePlaceholderText(originalText);
        const normalizedValue = normalizeText(value);
        const firstX = getFirstX(tspan);
        let combinedEndX = getOriginalSpan(tspan, measureTextLength(el)).end;

        if (normalizedValue === "") {
            return;
        }

        if (placeholder.hasUnderline) {
            const clone = getPlaceholderClone(root, el, fieldId);
            const cloneTspan = getPrimaryTspan(clone);
            if (cloneTspan) {
                const placeholderX = buildUnderlineXAttr(
                    tspan.dataset.origX || tspan.getAttribute("x"),
                    placeholder.prefix.length,
                    placeholder.underline.length
                );
                clone.style.display = "";
                clone.setAttribute("transform", el.dataset.origTransform || el.getAttribute("transform") || "");
                cloneTspan.textContent = placeholder.underline;
                cloneTspan.setAttribute("x", placeholderX);
                cloneTspan.setAttribute("y", tspan.dataset.origY || tspan.getAttribute("y") || "0");
                cloneTspan.dataset.placeholderOrigX = placeholderX;
                cloneTspan.style.textDecoration = "";
            }
        }

        tspan.textContent = placeholder.hasUnderline
            ? placeholder.prefix + normalizedValue + placeholder.suffix
            : normalizedValue;
        tspan.setAttribute("x", String(firstX));
        tspan.style.textDecoration = placeholder.hasUnderline ? "" : "underline";
        tspan.style.textUnderlineOffset = placeholder.hasUnderline ? "" : "2px";

        (groupedIds || []).forEach(function(extraId) {
            const extraEl = getTextElement(root, extraId);
            const extraTspan = getPrimaryTspan(extraEl);
            if (!extraEl || !extraTspan) {
                return;
            }

            combinedEndX = Math.max(combinedEndX, getOriginalSpan(extraTspan, measureTextLength(extraEl)).end);
            extraTspan.textContent = "";
            extraTspan.setAttribute("x", extraTspan.dataset.origX || "0");
            extraEl.style.display = "none";
        });

        el.dataset.svgGroupEndX = String(combinedEndX);
    }

    function getAffectedTransforms(root, fieldIds) {
        const transforms = new Set();
        fieldIds.forEach(function(id) {
            const el = getTextElement(root, id);
            if (!el) {
                return;
            }
            const transform = el.dataset.origTransform || el.getAttribute("transform") || "";
            if (transform !== "") {
                transforms.add(transform);
            }
        });
        return Array.from(transforms);
    }

    function getLineMetrics(tokens) {
        const metrics = new Map();

        tokens.forEach(function(token) {
            const y = token.origY;
            const startX = token.origFirstX;
            const endX = token.origEndX;

            if (!metrics.has(y)) {
                metrics.set(y, {
                    startX: startX,
                    maxEnd: endX
                });
                return;
            }

            const current = metrics.get(y);
            current.startX = Math.min(current.startX, startX);
            current.maxEnd = Math.max(current.maxEnd, endX);
        });

        return metrics;
    }

    function getTokenData(el, index) {
        const tspan = getPrimaryTspan(el);
        if (!tspan) {
            return null;
        }

        const originalText = tspan.dataset.origText || "";
        const firstVisibleX = getFirstX(tspan);
        const currentWidth = measureTextLength(el);
        const originalSpan = getOriginalSpan(tspan, currentWidth);
        const groupedEndX = parseFloat(el.dataset.svgGroupEndX || "");

        return {
            el: el,
            tspan: tspan,
            index: index,
            text: tspan.textContent || "",
            origText: originalText,
            origFirstX: firstVisibleX,
            origEndX: !isNaN(groupedEndX) ? Math.max(originalSpan.end, groupedEndX) : originalSpan.end,
            origY: getFirstY(tspan),
            currentWidth: currentWidth,
            leadingGap: 0
        };
    }

    function reflowTransformGroup(root, transform) {
        const elements = getOriginalTextElements(root).filter(function(el) {
            return (el.dataset.origTransform || el.getAttribute("transform") || "") === transform;
        });

        const tokens = elements
            .map(getTokenData)
            .filter(function(token) { return token !== null; })
            .sort(function(a, b) {
                if (a.origY !== b.origY) {
                    return a.origY - b.origY;
                }
                if (a.origFirstX !== b.origFirstX) {
                    return a.origFirstX - b.origFirstX;
                }
                return a.index - b.index;
            });

        if (tokens.length === 0) {
            return;
        }

        const laidOutTokens = tokens.filter(function(token) {
            return token.text !== "";
        });
        const lineMetrics = getLineMetrics(laidOutTokens);
        const lineYs = Array.from(lineMetrics.keys()).sort(function(a, b) { return a - b; });

        if (lineYs.length === 0) {
            return;
        }

        const previousByLine = new Map();
        laidOutTokens.forEach(function(token) {
            const priorToken = previousByLine.get(token.origY);
            token.leadingGap = priorToken
                ? Math.max(0, token.origFirstX - priorToken.origEndX)
                : 0;
            previousByLine.set(token.origY, token);
        });

        const lineState = lineYs.map(function(y) {
            return {
                y: y,
                startX: lineMetrics.get(y).startX,
                maxEnd: lineMetrics.get(y).maxEnd,
                cursorX: lineMetrics.get(y).startX,
                hasContent: false
            };
        });

        let currentLineIndex = 0;

        tokens.forEach(function(token) {
            const el = token.el;
            const tspan = token.tspan;
            const text = tspan.textContent || "";

            if (text === "") {
                el.style.display = "none";
                return;
            }

            const isWhitespace = text.trim() === "";

            let targetLineIndex = currentLineIndex;

            while (targetLineIndex < lineState.length) {
                const line = lineState[targetLineIndex];
                const candidateX = line.hasContent
                    ? line.cursorX + token.leadingGap
                    : line.startX;
                const projectedEnd = candidateX + token.currentWidth;
                const canWrap = targetLineIndex < lineState.length - 1;

                if (line.hasContent && projectedEnd > line.maxEnd && canWrap) {
                    targetLineIndex += 1;
                    continue;
                }

                break;
            }

            if (targetLineIndex >= lineState.length) {
                targetLineIndex = lineState.length - 1;
            }

            const line = lineState[targetLineIndex];
            const shouldHideLeadingWhitespace = isWhitespace && !line.hasContent;
            const nextX = line.hasContent
                ? line.cursorX + token.leadingGap
                : line.startX;

            if (shouldHideLeadingWhitespace) {
                el.style.display = "none";
                currentLineIndex = targetLineIndex;
                return;
            }

            tspan.setAttribute("x", String(nextX));
            tspan.setAttribute("y", String(line.y));
            el.style.display = "";
            if (el.dataset.origTransform) {
                el.setAttribute("transform", el.dataset.origTransform);
            }

            line.cursorX = nextX + token.currentWidth;
            if (!isWhitespace) {
                line.hasContent = true;
            }
            currentLineIndex = targetLineIndex;
        });
    }

    function syncPlaceholderPosition(root, fieldId) {
        const source = getTextElement(root, fieldId);
        const placeholder = getTextElement(root, fieldId + "-placeholder");
        const sourceTspan = getPrimaryTspan(source);
        const placeholderTspan = getPrimaryTspan(placeholder);

        if (!source || !placeholder || !sourceTspan || !placeholderTspan || placeholder.style.display === "none") {
            return;
        }

        placeholder.setAttribute("transform", source.getAttribute("transform") || source.dataset.origTransform || "");
        placeholderTspan.setAttribute("y", sourceTspan.getAttribute("y") || sourceTspan.dataset.origY || "0");
        const currentXCoords = parseXCoords(sourceTspan.getAttribute("x") || sourceTspan.dataset.origX || "0");
        const currentFirstX = currentXCoords.length > 0 ? currentXCoords[0] : 0;
        const baseFirstX = getFirstX(sourceTspan);
        const placeholderBaseCoords = parseXCoords(placeholderTspan.dataset.placeholderOrigX || placeholderTspan.getAttribute("x") || "0");
        if (placeholderBaseCoords.length > 0) {
            const shiftedCoords = placeholderBaseCoords.map(function(coord) {
                return coord + (currentFirstX - baseFirstX);
            });
            placeholderTspan.setAttribute("x", shiftedCoords.join(" "));
        }
    }

    function syncLayout(options) {
        const root = options && options.root ? options.root : document;
        const fieldIds = options && options.fieldIds ? options.fieldIds : [];
        const fieldGroups = options && options.fieldGroups ? options.fieldGroups : {};
        const getFieldValue = options && typeof options.getFieldValue === "function"
            ? options.getFieldValue
            : function() { return ""; };

        const previousRaf = rafByRoot.get(root);
        if (previousRaf) {
            cancelAnimationFrame(previousRaf);
        }

        resetOriginalLayout(root);

        fieldIds.forEach(function(id) {
            applyFieldValue(root, id, getFieldValue(id), fieldGroups[id] || []);
        });

        const rafId = requestAnimationFrame(function() {
            getAffectedTransforms(root, fieldIds).forEach(function(transform) {
                reflowTransformGroup(root, transform);
            });

            fieldIds.forEach(function(id) {
                syncPlaceholderPosition(root, id);
            });

            rafByRoot.delete(root);
            if (options && typeof options.afterLayout === "function") {
                options.afterLayout();
            }
        });

        rafByRoot.set(root, rafId);
        return rafId;
    }

    global.CommunaLinkDocumentSvg = {
        normalizeText: normalizeText,
        syncLayout: syncLayout
    };
})(window);
