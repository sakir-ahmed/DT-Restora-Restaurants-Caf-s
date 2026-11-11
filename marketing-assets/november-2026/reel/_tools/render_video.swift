// Renders a SaaS-style animated reel as raw RGBA frames on stdout (pipe into ffmpeg).
// Usage: render_video <spec.json>
// Everything is drawn with AppKit text so Bangla conjuncts shape correctly.
import AppKit

let W = 1080, H = 1920
let navy = NSColor(red: 0.035, green: 0.075, blue: 0.16, alpha: 1)
let gold = NSColor(red: 0.86, green: 0.68, blue: 0.25, alpha: 1)
let blue = NSColor(red: 0.20, green: 0.45, blue: 1.0, alpha: 1)

// MARK: easing
func clamp(_ x: Double) -> Double { min(1, max(0, x)) }
func outCubic(_ x: Double) -> Double { 1 - pow(1 - clamp(x), 3) }
func outBack(_ x: Double) -> Double { let c1 = 1.70158, c3 = c1 + 1, p = clamp(x) - 1; return 1 + c3 * p * p * p + c1 * p * p }
func inOut(_ x: Double) -> Double { let p = clamp(x); return p < 0.5 ? 4 * p * p * p : 1 - pow(-2 * p + 2, 3) / 2 }
func lerp(_ a: Double, _ b: Double, _ t: Double) -> Double { a + (b - a) * t }

// MARK: text
func bn(_ size: CGFloat) -> NSFont { NSFont(name: "KohinoorBangla-Semibold", size: size) ?? .boldSystemFont(ofSize: size) }
func lat(_ size: CGFloat) -> NSFont { NSFont(name: "HelveticaNeue-Bold", size: size) ?? .boldSystemFont(ofSize: size) }
func attr(_ s: String, _ f: NSFont, _ c: NSColor) -> NSAttributedString { NSAttributedString(string: s, attributes: [.font: f, .foregroundColor: c]) }

struct Word { let s: String; let x: CGFloat; let y: CGFloat; let w: CGFloat }
struct Block { let words: [Word]; let height: CGFloat; let lineH: CGFloat }

func layout(_ text: String, font: NSFont, maxW: CGFloat) -> Block {
    let parts = text.split(separator: " ").map(String.init)
    let space = attr(" ", font, .white).size().width
    let lineH = font.pointSize * 1.5
    var lines: [[(String, CGFloat)]] = [[]]
    var cur: CGFloat = 0
    for p in parts {
        let w = attr(p, font, .white).size().width
        if cur > 0 && cur + space + w > maxW { lines.append([]); cur = 0 }
        lines[lines.count - 1].append((p, w))
        cur += (cur > 0 ? space : 0) + w
    }
    var out: [Word] = []
    for (i, line) in lines.enumerated() {
        let lw = line.reduce(CGFloat(0)) { $0 + $1.1 } + space * CGFloat(max(0, line.count - 1))
        var x = (maxW - lw) / 2
        for (s, w) in line { out.append(Word(s: s, x: x, y: CGFloat(i) * lineH, w: w)); x += w + space }
    }
    return Block(words: out, height: CGFloat(lines.count) * lineH, lineH: lineH)
}

// MARK: model
struct Scene {
    var kind: String
    var start: Double, end: Double
    var head: String, sub: String
    var img: NSImage?
    var cx: Double, cy: Double, z: Double
    var aStart: Double, aEnd: Double
    var headBlock: Block, subBlock: Block
    var first: Bool
    var cta: [String: Any]
    var callouts: [[String: Any]]
}

let spec = try! JSONSerialization.jsonObject(with: Data(contentsOf: URL(fileURLWithPath: CommandLine.arguments[1]))) as! [String: Any]
let fps = spec["fps"] as! Double
let total = spec["total"] as! Double
var scenes: [Scene] = []
for (i, s) in (spec["scenes"] as! [[String: Any]]).enumerated() {
    let kind = s["kind"] as! String
    let head = s["head"] as? String ?? ""
    let sub = s["sub"] as! String
    let first = i == 0
    scenes.append(Scene(
        kind: kind, start: s["start"] as! Double, end: s["end"] as! Double, head: head, sub: sub,
        img: (s["shot"] as? String).flatMap { NSImage(contentsOfFile: $0) },
        cx: s["cx"] as? Double ?? 0.5, cy: s["cy"] as? Double ?? 0.4, z: s["z"] as? Double ?? 1.4,
        aStart: s["audio_start"] as! Double, aEnd: s["audio_end"] as! Double,
        headBlock: layout(head, font: bn(first ? 104 : 88), maxW: 940),
        subBlock: layout(sub, font: bn(50), maxW: 880),
        first: first, cta: s["cta"] as? [String: Any] ?? [:], callouts: s["callouts"] as? [[String: Any]] ?? []))
}

// MARK: drawing context
let cg = CGContext(data: nil, width: W, height: H, bitsPerComponent: 8, bytesPerRow: W * 4,
                   space: CGColorSpace(name: CGColorSpace.sRGB)!, bitmapInfo: CGImageAlphaInfo.premultipliedLast.rawValue)!
cg.translateBy(x: 0, y: CGFloat(H)); cg.scaleBy(x: 1, y: -1)
NSGraphicsContext.current = NSGraphicsContext(cgContext: cg, flipped: true)

func glow(_ x: Double, _ y: Double, _ r: Double, _ c: NSColor, _ a: Double) {
    let p = NSPoint(x: x, y: y)
    NSGradient(colors: [c.withAlphaComponent(a), c.withAlphaComponent(0)])!.draw(fromCenter: p, radius: 0, toCenter: p, radius: r, options: [])
}

func rr(_ r: NSRect, _ rad: CGFloat) -> NSBezierPath { NSBezierPath(roundedRect: r, xRadius: rad, yRadius: rad) }

func background(_ t: Double) {
    navy.setFill(); NSRect(x: 0, y: 0, width: W, height: H).fill()
    glow(220 + 140 * sin(t * 0.5), 420 + 90 * cos(t * 0.4), 700, gold, 0.16)
    glow(900 + 120 * cos(t * 0.35), 1250 + 110 * sin(t * 0.45), 900, blue, 0.30)
    glow(540, 1850, 700, blue, 0.20)
    NSColor(white: 1, alpha: 0.045).setFill()
    let off = (t * 14).truncatingRemainder(dividingBy: 60)
    var y = -60 + off
    while y < Double(H) { var x = 30.0; while x < Double(W) { NSBezierPath(ovalIn: NSRect(x: x, y: y, width: 3, height: 3)).fill(); x += 60 }; y += 60 }
}

func chrome(_ t: Double) {
    NSColor(white: 1, alpha: 0.14).setFill(); rr(NSRect(x: 60, y: 62, width: 960, height: 6), 3).fill()
    gold.setFill(); rr(NSRect(x: 60, y: 62, width: max(8, 960 * t / total), height: 6), 3).fill()
    attr("DT Restora", lat(38), gold).draw(at: NSPoint(x: 60, y: 90))
}

func cursor(at p: NSPoint, alpha: Double) {
    guard alpha > 0 else { return }
    NSGraphicsContext.current!.saveGraphicsState()
    let sh = NSShadow(); sh.shadowBlurRadius = 10; sh.shadowColor = NSColor(white: 0, alpha: 0.5 * alpha); sh.shadowOffset = NSSize(width: 0, height: 6); sh.set()
    let pts: [(CGFloat, CGFloat)] = [(0, 0), (0, 26), (7, 20), (12, 32), (17, 30), (12, 19), (21, 19)]
    let path = NSBezierPath()
    for (i, q) in pts.enumerated() {
        let pt = NSPoint(x: p.x + q.0 * 1.7, y: p.y + q.1 * 1.7)
        i == 0 ? path.move(to: pt) : path.line(to: pt)
    }
    path.close()
    NSColor(white: 1, alpha: alpha).setFill(); path.fill()
    NSColor(white: 0, alpha: alpha).setStroke(); path.lineWidth = 2; path.stroke()
    NSGraphicsContext.current!.restoreGraphicsState()
}

func drawWords(_ b: Block, origin: NSPoint, font: NSFont, color: (Int, Word) -> NSColor, anim: (Int) -> (Double, CGFloat)) {
    for (i, w) in b.words.enumerated() {
        let (a, dy) = anim(i)
        if a <= 0.001 { continue }
        let c = color(i, w).withAlphaComponent(CGFloat(a) * color(i, w).alphaComponent)
        attr(w.s, font, c).draw(at: NSPoint(x: origin.x + w.x, y: origin.y + w.y + dy))
    }
}

func isAccent(_ s: String) -> Bool {
    s.contains("?") || s.unicodeScalars.contains { ("0"..."9").contains(Character($0)) || (0x09E6...0x09EF).contains($0.value) }
}

// MARK: scene
let card = NSRect(x: 50, y: 540, width: 980, height: 820)
let barH: CGFloat = 46

func drawScene(_ s: Scene, _ t: Double) {
    let lt = t - s.start, d = s.end - s.start
    let q = clamp((t - s.end) / 0.4)          // exit progress (overlaps the next scene's entrance)
    let fadeIn = clamp(lt / 0.3), vis = fadeIn * (1 - q)
    if vis <= 0.001 { return }

    // headline: word-by-word pop
    let hf = bn(s.first ? 104 : 88)
    let hy = 350 - s.headBlock.height / 2
    drawWords(s.headBlock, origin: NSPoint(x: 70, y: hy), font: hf,
              color: { _, w in isAccent(w.s) ? gold : .white },
              anim: { i in
                  let p = clamp((lt - 0.05 - Double(i) * 0.10) / 0.5)
                  return (outCubic(p * 1.6) * (1 - q), CGFloat((1 - outBack(p)) * 60 - q * 40))
              })

    if s.kind == "cta" { drawCTA(s, lt, q) } else { drawCard(s, lt, d, q, vis) }

    // karaoke subtitle
    let sb = s.subBlock
    let boxH = sb.height + 56
    let boxY = 1490 - boxH / 2
    let boxA = outCubic((lt - 0.2) / 0.4) * (1 - q)
    NSColor(red: 0.02, green: 0.04, blue: 0.10, alpha: 0.62 * boxA).setFill()
    rr(NSRect(x: 60, y: boxY + CGFloat((1 - outCubic((lt - 0.2) / 0.4)) * 30), width: 960, height: boxH), 34).fill()
    let wts = sb.words.map { Double($0.s.count) + 1 }
    let sum = wts.reduce(0, +)
    var acc = 0.0
    var times: [(Double, Double)] = []
    for w in wts { times.append((s.aStart + (s.aEnd - s.aStart) * acc / sum, s.aStart + (s.aEnd - s.aStart) * (acc + w) / sum)); acc += w }
    drawWords(sb, origin: NSPoint(x: 100, y: boxY + 28 + CGFloat((1 - outCubic((lt - 0.2) / 0.4)) * 30)), font: bn(50),
              color: { i, _ in
                  let (a, b) = times[i]
                  if t >= a && t < b { return gold }
                  return t >= b ? .white : NSColor(white: 1, alpha: 0.38)
              },
              anim: { _ in (boxA, 0) })
}

func drawCard(_ s: Scene, _ lt: Double, _ d: Double, _ q: Double, _ vis: Double) {
    let enter = outBack(clamp((lt - 0.15) / 0.75))
    let a = clamp((lt - 0.1) / 0.35) * (1 - q)
    let scale = CGFloat(lerp(0.9, 1, enter) * (1 - 0.05 * q))
    let dy = CGFloat((1 - enter) * 260 - q * 140)
    let ctx = NSGraphicsContext.current!
    ctx.saveGraphicsState()
    let cxp = card.midX, cyp = card.midY + dy
    let tf = NSAffineTransform(); tf.translateX(by: cxp, yBy: cyp); tf.scaleX(by: scale, yBy: scale); tf.translateX(by: -card.midX, yBy: -card.midY); tf.concat()
    ctx.cgContext.setAlpha(CGFloat(a))

    glow(Double(card.midX), Double(card.midY), 700, gold, 0.10)
    ctx.saveGraphicsState()
    let sh = NSShadow(); sh.shadowBlurRadius = 60; sh.shadowColor = NSColor(white: 0, alpha: 0.6); sh.shadowOffset = NSSize(width: 0, height: 30); sh.set()
    NSColor.white.setFill(); rr(card, 34).fill()
    ctx.restoreGraphicsState()

    ctx.saveGraphicsState()
    rr(card, 34).addClip()
    NSColor(red: 0.93, green: 0.94, blue: 0.97, alpha: 1).setFill(); NSRect(x: card.minX, y: card.minY, width: card.width, height: barH).fill()
    for (i, c) in [NSColor(red: 1, green: 0.37, blue: 0.34, alpha: 1), NSColor(red: 1, green: 0.74, blue: 0.18, alpha: 1), NSColor(red: 0.16, green: 0.78, blue: 0.25, alpha: 1)].enumerated() {
        c.setFill(); NSBezierPath(ovalIn: NSRect(x: card.minX + 22 + CGFloat(i) * 26, y: card.minY + 15, width: 16, height: 16)).fill()
    }
    NSColor.white.setFill(); rr(NSRect(x: card.minX + 150, y: card.minY + 8, width: card.width - 300, height: 30), 15).fill()
    attr("dt-restora.datatechbd.com", NSFont.systemFont(ofSize: 18, weight: .medium), NSColor(white: 0.4, alpha: 1)).draw(at: NSPoint(x: card.minX + 150 + (card.width - 300) / 2 - 105, y: card.minY + 13))

    let content = NSRect(x: card.minX, y: card.minY + barH, width: card.width, height: card.height - barH)
    var view = (x: 0.0, y: 0.0, w: 1.0, h: 1.0, iw: 1.0, ih: 1.0)
    if let img = s.img {
        let iw = Double(img.size.width), ih = Double(img.size.height)
        let aspect = Double(content.width / content.height)
        let h0 = ih, w0 = h0 * aspect
        let zt = inOut((lt - 0.7) / min(2.6, max(1.2, d - 1.2)))
        let z = lerp(1, s.z, zt)
        let vw = w0 / z, vh = h0 / z
        let tcx = min(max(s.cx * iw, vw / 2), iw - vw / 2), tcy = min(max(s.cy * ih, vh / 2), ih - vh / 2)
        let vcx = lerp(iw / 2, tcx, zt), vcy = lerp(ih / 2, tcy, zt)
        let src = NSRect(x: vcx - vw / 2, y: ih - (vcy + vh / 2), width: vw, height: vh)
        img.draw(in: content, from: src, operation: .sourceOver, fraction: 1, respectFlipped: true, hints: [.interpolation: NSImageInterpolation.high])
        view = (vcx - vw / 2, vcy - vh / 2, vw, vh, iw, ih)
    }
    ctx.restoreGraphicsState()

    // where a point of the screenshot (0...1 in both axes) is on the card right now
    func map(_ sx: Double, _ sy: Double) -> NSPoint {
        NSPoint(x: content.minX + CGFloat((sx * view.iw - view.x) / view.w) * content.width,
                y: content.minY + CGFloat((sy * view.ih - view.y) / view.h) * content.height)
    }
    let first = s.callouts.first
    let tgt = first.map { map($0["sx"] as! Double, $0["sy"] as! Double) } ?? NSPoint(x: content.midX + 150, y: content.midY + 70)
    let tapAt = (first?["delay"] as? Double ?? 1.3)
    let from = NSPoint(x: content.maxX - 70, y: content.maxY - 60)
    let m = inOut((lt - (tapAt - 0.9)) / 0.9)
    let pos = NSPoint(x: lerp(Double(from.x), Double(tgt.x), m), y: lerp(Double(from.y), Double(tgt.y), m))

    // callouts: pulsing ring on the target and a bubble that pops in
    for c in s.callouts {
        let delay = c["delay"] as? Double ?? 1.3
        let p = clamp((lt - delay) / 0.5)
        if p <= 0 { continue }
        let t0 = map(c["sx"] as! Double, c["sy"] as! Double)
        let pulse = ((lt - delay).truncatingRemainder(dividingBy: 1.4)) / 1.4
        gold.withAlphaComponent(CGFloat(0.9 * (1 - pulse))).setStroke()
        let rr0 = CGFloat(18 + 46 * pulse); let ring = NSBezierPath(ovalIn: NSRect(x: t0.x - rr0, y: t0.y - rr0, width: rr0 * 2, height: rr0 * 2)); ring.lineWidth = 5; ring.stroke()
        gold.setFill(); NSBezierPath(ovalIn: NSRect(x: t0.x - 9, y: t0.y - 9, width: 18, height: 18)).fill()
        let text = c["text"] as! String
        let att = attr(text, bn(40), .white); let sz = att.size()
        let bw = sz.width + 56, bh: CGFloat = 80
        let above = t0.y - content.minY > 260
        let by = above ? t0.y - 175 : t0.y + 110
        let lineA = CGFloat(clamp((lt - delay) / 0.5)) * CGFloat(1 - q)
        gold.withAlphaComponent(0.85 * lineA).setStroke()
        let ln = NSBezierPath(); ln.move(to: NSPoint(x: t0.x, y: above ? t0.y - 18 : t0.y + 18)); ln.line(to: NSPoint(x: t0.x, y: above ? by + 80 : by)); ln.lineWidth = 3; ln.stroke()
        let bx = min(max(t0.x - bw / 2, card.minX + 24), card.maxX - 24 - bw)
        NSGraphicsContext.current!.saveGraphicsState()
        let a2 = CGFloat(outCubic(p * 1.5)) * CGFloat(1 - q)
        let sc = CGFloat(lerp(0.8, 1, outBack(p)))
        let tf2 = NSAffineTransform(); tf2.translateX(by: bx + bw / 2, yBy: by + bh / 2); tf2.scaleX(by: sc, yBy: sc); tf2.translateX(by: -(bx + bw / 2), yBy: -(by + bh / 2)); tf2.concat()
        let sh2 = NSShadow(); sh2.shadowBlurRadius = 24; sh2.shadowColor = NSColor(white: 0, alpha: 0.5 * a2); sh2.shadowOffset = NSSize(width: 0, height: 10); sh2.set()
        navy.withAlphaComponent(0.95 * a2).setFill(); rr(NSRect(x: bx, y: by, width: bw, height: bh), 40).fill()
        NSGraphicsContext.current!.restoreGraphicsState()
        gold.withAlphaComponent(a2).setStroke(); let bp2 = rr(NSRect(x: bx, y: by, width: bw, height: bh), 40); bp2.lineWidth = 3; bp2.stroke()
        attr(text, bn(40), NSColor.white.withAlphaComponent(a2)).draw(at: NSPoint(x: bx + 28, y: by + (bh - sz.height) / 2))
    }
    // cursor glides in, then taps with a ripple
    if lt > tapAt {
        let r = ((lt - tapAt).truncatingRemainder(dividingBy: 1.3)) / 0.9
        if r < 1 {
            gold.withAlphaComponent(CGFloat(0.9 * (1 - r))).setStroke()
            let rad = CGFloat(14 + 70 * outCubic(r)); let p = NSBezierPath(ovalIn: NSRect(x: tgt.x - rad, y: tgt.y - rad, width: rad * 2, height: rad * 2)); p.lineWidth = 6; p.stroke()
        }
    }
    cursor(at: pos, alpha: clamp((lt - (tapAt - 1.0)) / 0.2) * (1 - q))
    ctx.restoreGraphicsState()
}

func drawCTA(_ s: Scene, _ lt: Double, _ q: Double) {
    let c = s.cta
    func pop(_ k: Int) -> (Double, CGFloat) {
        let p = clamp((lt - 0.25 - Double(k) * 0.16) / 0.55)
        return (outCubic(p * 1.5) * (1 - q), CGFloat((1 - outBack(p)) * 70))
    }
    func centered(_ text: String, font: NSFont, color: NSColor, y: CGFloat, k: Int, maxW: CGFloat = 980) {
        var f = font
        var size = attr(text, f, color).size()
        while size.width > maxW { f = NSFont(name: f.fontName, size: f.pointSize - 2)!; size = attr(text, f, color).size() }
        let (a, dy) = pop(k)
        attr(text, f, color.withAlphaComponent(CGFloat(a))).draw(at: NSPoint(x: (CGFloat(W) - size.width) / 2, y: y + dy))
    }
    centered(c["brand"] as! String, font: lat(130), color: gold, y: 330, k: 0)
    // pulsing demo button
    let (ba, bdy) = pop(1)
    let pulse = 1 + 0.035 * sin(lt * 5)
    let bw: CGFloat = 640 * CGFloat(pulse), bh: CGFloat = 130 * CGFloat(pulse)
    let br = NSRect(x: (CGFloat(W) - bw) / 2, y: 540 + bdy - (bh - 130) / 2, width: bw, height: bh)
    NSGraphicsContext.current!.saveGraphicsState()
    let sh = NSShadow(); sh.shadowBlurRadius = 40; sh.shadowColor = gold.withAlphaComponent(CGFloat(0.5 * ba)); sh.shadowOffset = .zero; sh.set()
    gold.withAlphaComponent(CGFloat(ba)).setFill(); rr(br, 65).fill()
    NSGraphicsContext.current!.restoreGraphicsState()
    let pill = c["pill"] as! String
    let ps = attr(pill, bn(68), navy.withAlphaComponent(CGFloat(ba))); let psz = ps.size()
    ps.draw(at: NSPoint(x: br.midX - psz.width / 2, y: br.midY - psz.height / 2))
    // info rows
    let rows: [(String, NSFont, NSColor)] = [
        (c["url"] as! String, lat(52), .white), (c["contact"] as! String, bn(52), gold), (c["email"] as! String, lat(48), .white)]
    for (i, r) in rows.enumerated() {
        let y: CGFloat = 760 + CGFloat(i) * 105
        let (a, dy) = pop(2 + i)
        NSColor(white: 1, alpha: 0.08 * CGFloat(a)).setFill(); rr(NSRect(x: 70, y: y + dy - 8, width: 940, height: 92), 46).fill()
        centered(r.0, font: r.1, color: r.2, y: y + 6, k: 2 + i, maxW: 880)
    }
    // price badge
    let (pa, pdy) = pop(6)
    let box = NSRect(x: 110, y: 1105 + pdy, width: 860, height: 190)
    gold.withAlphaComponent(CGFloat(0.9 * pa)).setStroke()
    let bp = rr(box, 36); bp.lineWidth = 4; bp.stroke()
    let pl = c["price"] as! [String]
    for (i, line) in pl.enumerated() {
        let a = attr(line, bn(50), NSColor.white.withAlphaComponent(CGFloat(pa)))
        let sz = a.size()
        a.draw(at: NSPoint(x: box.midX - sz.width / 2, y: box.minY + 22 + CGFloat(i) * 78))
    }
}

// MARK: main loop
let frames = Int(total * fps)
let out = FileHandle.standardOutput
let bytes = W * H * 4
for f in 0..<frames {
    let t = Double(f) / fps
    background(t)
    chrome(t)
    for s in scenes where t >= s.start && t < s.end + 0.4 { drawScene(s, t) }
    out.write(Data(bytesNoCopy: cg.data!, count: bytes, deallocator: .none))
}
