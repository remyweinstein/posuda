/* global d, Window, Document */

const C = function (s, p) {
    this.isC = true,
        this.isNodeList = (nodes) => {
            const stringRepr = Object.prototype.toString.call(nodes);

            return typeof nodes === 'object' &&
                /^\[object (HTMLCollection|NodeList|Object)\]$/.test(stringRepr) &&
                (typeof nodes.length === 'number') &&
                (nodes.length === 0 || (typeof nodes[0] === 'object' && nodes[0].nodeType > 0));
        },
        this.isNode = (obj) => {
            if (obj && obj.nodeType) {
                return true;
            } else {
                return false;
            }
        },
        this.isDocument = (obj) => {
            return obj instanceof Document || obj instanceof Window;
        },
        this.isclass = (cl) => {
            return this.els[0].classList.contains(cl);
        },
        this.defineEls = () => {
            if (this.isNode(s) || this.isDocument(s)) {
                return [s];
            } else if (this.isNodeList(s)) {
                return s;
            } else {
                if (p && p.isC) {
                    p = p.els[0];
                }

                return this.isNode(p) ? p.querySelectorAll(s) : d.querySelectorAll(s);
            }
        },
        this.defineEl = () => {
            return this.els[0];
        },
        this.els = this.defineEls(),
        this.el = this.defineEl(),
        this.on = (type, s, fn, except) => {
            const p = this;
            let i;

            this.bind(type, (e) => {
                const el = (p.isNode(s) || p.isNodeList(s)) ? s : C(s).els,
                    ex = except || false;
                let t = e.target;

                while (t && t !== this) {
                    if (ex) {
                        for (i = 0; i < C(ex).els.length; i++) {
                            if (t === C(ex).els[i]) {
                                break;
                            }
                        }
                    }

                    for (i = 0; i < el.length; i++) {
                        if (t === el[i]) {
                            fn(e, t);
                            break;
                        }
                    }

                    if (t) {
                        t = t.parentNode;
                    } else {
                        break;
                    }
                }
            });

            return this;
        },
        this.strToNode = (h) => {
            let terk;

            if (!this.isNode(h)) {
                const div = this.create('div');

                div.html(h);
                terk = [div.el.children[0]];
            } else {
                terk = [h];
            }

            this.els = terk;
            this.el = terk[0];

            return this;
        },
        this.attr = (attr, value) => {
            if (!value) {
                return this.el.getAttribute(attr);
            }

            this.els.forEach((el) => {
                el.setAttribute(attr, value);
            });

            return this;
        },
        this.create = (tag) => {
            const el = d.createElement(tag);
            this.els = [el];
            this.el  = el;

            return this;
        },
        this.append = (el) => {
            this.el.append(el.el);
        },
        this.style = (st, val) => {
            this.els.forEach((el) => {
                el.style[st] = val;
            });

            return this;
        },
        this.addclass = (cls) => {
            if (!cls) {
                return;
            }
            
            if (!Array.isArray(cls)) {
                cls = [cls];
            }

            this.els.forEach((el) => {
                cls.forEach((cl) => {
                    el.classList.add(cl);
                });
            });

            return this;
        },
        this.togclass = (cl) => {
            this.els.forEach((el) => {
                el.classList.toggle(cl);
            });

            return this;
        },
        this.delclass = (cls) => {
            if (!Array.isArray(cls)) {
                cls = [cls];
            }

            this.els.forEach((el) => {
                cls.forEach((cl) => {
                    el.classList.remove(cl);
                });
            });

            return this;
        },
        this.remove = (el) => {
            let elem = el.el;
    
            if (d.body.contains(elem)) {
                elem.parentNode.removeChild(elem);
            }
            
            return this;
        },
        this.delStor = (key) => {
            localStorage.removeItem(key);
            return this;
        },
        this.setStor = (key, val) => {
            localStorage.setItem(key, val);
            return this;
        },
        this.getStor = (key) => {
            return localStorage.getItem(key);
        },
        this.bind = (type, fn) => {
            let addEvent;

            if (!type || !fn) {
                return this;
            }

            if (typeof addEventListener === 'function') {
                addEvent = (el, type, fn) => {
                    el.addEventListener(type, fn, false);
                };
            } else if (typeof attachEvent === 'function') {
                addEvent = (el, type, fn) => {
                    el.attachEvent(`on${type}`, fn);
                };
            } else {
                return this;
            }

            if (this.isNodeList(this.els) || this.els.length > 0) {
                this.els.forEach((el) => {
                    addEvent(el, type, fn);
                });
            } else if (this.isNode(this.els[0]) || this.isDocument(this.els[0])) {
                addEvent(this.els[0], type, fn);
            }

            return this;
        },
        this.html = (html) => {
            if (html !== '' && !html) {
                return this.els[0].innerHTML;
            }

            this.els.forEach((el) => {
                el.innerHTML = html;
            });

            return this;
        },
        this.text = (text) => {
            if (text !== '' && !text) {
                return this.els[0].innerText;
            }

            this.els.forEach((el) => {
                el.innerText = text;
            });

            return this;
        },
        this.val = (value) => {
            if (value !== '' && !value) {
                return this.els[0].value;
            }

            this.els.forEach((el) => {
                el.value = value;
            });

            return this;
        };

    if (this instanceof C) {
        return this.C;
    } else {
        return new C(s, p);
    }

};