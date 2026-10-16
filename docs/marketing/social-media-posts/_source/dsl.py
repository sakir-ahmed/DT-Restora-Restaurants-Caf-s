def P(cat, slug, tpl, title, sub, bullets, shot, caption, tags, kw, **extra):
    d = dict(cat=cat, slug=slug, tpl=tpl, title=title, sub=sub, bullets=bullets, shot=shot, caption=caption, tags=tags, kw=kw)
    d.update(extra)
    return d


def S(head, text, shot=None, **extra):
    d = dict(head=head, text=text, shot=shot)
    d.update(extra)
    return d
