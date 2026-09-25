#!/usr/bin/env python3
"""
minisecrets — мінімальний заступник gnome-keyring (org.freedesktop.secrets)
без жодних діалогів. На headless-сервері діалоги підтвердження gnome-keyring
ніхто не може натиснути, тому gnome-remote-desktop не міг зберегти пароль.

Секрети зберігаються у ~/.local/share/minisecrets.json (права 0600).
Сумісний із діалектом D-Bus gnome-keyring (перевірено інтроспекцією 49.x).
"""
import json
import os
import time

import gi
gi.require_version('Gio', '2.0')
gi.require_version('GLib', '2.0')
from gi.repository import Gio, GLib

NAME = 'org.freedesktop.secrets'
ROOT = '/org/freedesktop/secrets'
COL = ROOT + '/collection/default'
ALIAS = ROOT + '/aliases/default'
SESS = ROOT + '/session/p1'
STORE = os.path.expanduser('~/.local/share/minisecrets.json')

STATE = {'items': {}, 'next': 1}
if os.path.exists(STORE):
    try:
        with open(STORE) as f:
            STATE.update(json.load(f))
    except Exception:
        pass


def save():
    os.makedirs(os.path.dirname(STORE), exist_ok=True)
    tmp = STORE + '.tmp'
    fd = os.open(tmp, os.O_CREAT | os.O_WRONLY | os.O_TRUNC, 0o600)
    with os.fdopen(fd, 'w') as f:
        json.dump(STATE, f)
    os.replace(tmp, STORE)


conn = Gio.bus_get_sync(Gio.BusType.SESSION, None)
loop = GLib.MainLoop()
REG_IDS = []


def emit(path, iface, sig, params):
    try:
        conn.emit_signal(None, path, iface, sig, params)
    except Exception:
        pass


def unknown(inv, method):
    inv.return_dbus_error('org.freedesktop.DBus.Error.UnknownMethod', str(method))


def match_items(query):
    return [p for p, it in STATE['items'].items()
            if all(it['attrs'].get(k) == v for k, v in query.items())]


# ---------------------------------------------------------------- Service
SVC_XML = """<interface name="org.freedesktop.Secret.Service">
 <method name="OpenSession"><arg type="s" direction="in"/><arg type="v" direction="in"/><arg type="v" direction="out"/><arg type="o" direction="out"/></method>
 <method name="CreateCollection"><arg type="a{sv}" direction="in"/><arg type="s" direction="in"/><arg type="o" direction="out"/><arg type="o" direction="out"/></method>
 <method name="SearchItems"><arg type="a{ss}" direction="in"/><arg type="ao" direction="out"/><arg type="ao" direction="out"/></method>
 <method name="Unlock"><arg type="ao" direction="in"/><arg type="ao" direction="out"/><arg type="o" direction="out"/></method>
 <method name="Lock"><arg type="ao" direction="in"/><arg type="ao" direction="out"/><arg type="o" direction="out"/></method>
 <method name="LockService"/>
 <method name="GetSecrets"><arg type="ao" direction="in"/><arg type="o" direction="in"/><arg type="a{o(oayays)}" direction="out"/></method>
 <method name="ReadAlias"><arg type="s" direction="in"/><arg type="o" direction="out"/></method>
 <method name="SetAlias"><arg type="s" direction="in"/><arg type="o" direction="in"/></method>
 <signal name="CollectionCreated"><arg type="o"/></signal>
 <signal name="CollectionDeleted"><arg type="o"/></signal>
 <signal name="CollectionChanged"><arg type="o"/></signal>
 <property name="Collections" type="ao" access="read"/>
</interface>"""


def svc_method(c, sender, path, iface, method, params, inv):
    if method == 'OpenSession':
        mech = params.get_child_value(0).unpack()
        if mech != 'plain':
            inv.return_dbus_error('org.freedesktop.DBus.Error.NotSupported',
                                   'only plain session')
            return
        inv.return_value(GLib.Variant('(vo)', (GLib.Variant('s', ''), SESS)))
    elif method == 'CreateCollection':
        inv.return_value(GLib.Variant('(oo)', (COL, '/')))
    elif method == 'SearchItems':
        found = match_items(params.get_child_value(0).unpack())
        inv.return_value(GLib.Variant('(aoao)', (found, [])))
    elif method == 'Unlock':
        objs = params.get_child_value(0).unpack()
        inv.return_value(GLib.Variant('(aoo)', (objs, '/')))
    elif method == 'Lock':
        inv.return_value(GLib.Variant('(aoo)', ([], '/')))
    elif method == 'LockService':
        inv.return_value(None)
    elif method == 'GetSecrets':
        items = params.get_child_value(0).unpack()
        sess = params.get_child_value(1).unpack()
        d = {}
        for p in items:
            it = STATE['items'].get(p)
            if it:
                d[p] = (sess, bytes(it['f2']), bytes(it['f3']), it['f4'])
        inv.return_value(GLib.Variant('(a{o(oayays)})', (d,)))
    elif method == 'ReadAlias':
        inv.return_value(GLib.Variant('(o)', (COL,)))
    elif method == 'SetAlias':
        inv.return_value(None)
    else:
        unknown(inv, method)


def svc_prop(c, info, pinfo, path, *a):
    n = pinfo if isinstance(pinfo, str) else pinfo.name
    if n == 'Collections':
        return GLib.Variant('ao', [COL])
    return None


# ---------------------------------------------------------------- Collection
COL_XML = """<interface name="org.freedesktop.Secret.Collection">
 <method name="Delete"><arg type="o" direction="out"/></method>
 <method name="SearchItems"><arg type="a{ss}" direction="in"/><arg type="ao" direction="out"/></method>
 <method name="CreateItem"><arg type="a{sv}" direction="in"/><arg type="(oayays)" direction="in"/><arg type="b" direction="in"/><arg type="o" direction="out"/><arg type="o" direction="out"/></method>
 <signal name="ItemCreated"><arg type="o"/></signal>
 <signal name="ItemDeleted"><arg type="o"/></signal>
 <signal name="ItemChanged"><arg type="o"/></signal>
 <property name="Items" type="ao" access="read"/>
 <property name="Label" type="s" access="readwrite"/>
 <property name="Locked" type="b" access="read"/>
 <property name="Created" type="t" access="read"/>
 <property name="Modified" type="t" access="read"/>
</interface>"""


def col_method(c, sender, path, iface, method, params, inv):
    if method == 'Delete':
        inv.return_value(GLib.Variant('(o)', ('/',)))
    elif method == 'SearchItems':
        found = match_items(params.get_child_value(0).unpack())
        inv.return_value(GLib.Variant('(ao)', (found,)))
    elif method == 'CreateItem':
        props = params.get_child_value(0).unpack()
        secret = params.get_child_value(1).unpack()
        replace = params.get_child_value(2).unpack()
        label = props.get('org.freedesktop.Secret.Item.Label', '')
        attrs = props.get('org.freedesktop.Secret.Item.Attributes', {})
        ctype = props.get('org.freedesktop.Secret.Item.ContentType', '')
        target = None
        if replace:
            for p, it in STATE['items'].items():
                if it['attrs'] == attrs:
                    target = p
                    break
        new = target is None
        if new:
            target = '%s/item/i%d' % (ROOT, STATE['next'])
            STATE['next'] += 1
        STATE['items'][target] = {
            'label': label, 'type': ctype, 'attrs': attrs,
            'f2': list(secret[1]), 'f3': list(secret[2]), 'f4': secret[3],
            'created': int(time.time()), 'modified': int(time.time()),
        }
        save()
        if new:
            reg(target, ITEM_XML, item_method, item_prop)
            emit(COL, 'org.freedesktop.Secret.Collection', 'ItemCreated',
                 GLib.Variant('(o)', (target,)))
        else:
            emit(COL, 'org.freedesktop.Secret.Collection', 'ItemChanged',
                 GLib.Variant('(o)', (target,)))
        inv.return_value(GLib.Variant('(oo)', (target, '/')))
    else:
        unknown(inv, method)


def col_prop(c, info, pinfo, path, *a):
    n = pinfo if isinstance(pinfo, str) else pinfo.name
    if n == 'Items':
        return GLib.Variant('ao', list(STATE['items']))
    if n == 'Label':
        return GLib.Variant('s', 'default')
    if n == 'Locked':
        return GLib.Variant('b', False)
    if n == 'Created':
        return GLib.Variant('t', int(os.path.getmtime(STORE)) if os.path.exists(STORE) else int(time.time()))
    if n == 'Modified':
        return GLib.Variant('t', int(time.time()))
    return None


# ---------------------------------------------------------------- Item
ITEM_XML = """<interface name="org.freedesktop.Secret.Item">
 <method name="Delete"><arg type="o" direction="out"/></method>
 <method name="SetSecret"><arg type="(oayays)" direction="in"/></method>
 <property name="Label" type="s" access="read"/>
 <property name="Type" type="s" access="read"/>
 <property name="Attributes" type="a{ss}" access="read"/>
 <property name="Created" type="t" access="read"/>
 <property name="Modified" type="t" access="read"/>
 <property name="Secret" type="(oayays)" access="read"/>
 <property name="Locked" type="b" access="read"/>
</interface>"""


def item_method(c, sender, path, iface, method, params, inv):
    it = STATE['items'].get(path)
    if it is None:
        inv.return_dbus_error('org.freedesktop.DBus.Error.UnknownObject', path)
        return
    if method == 'Delete':
        del STATE['items'][path]
        save()
        emit(COL, 'org.freedesktop.Secret.Collection', 'ItemDeleted',
             GLib.Variant('(o)', (path,)))
        inv.return_value(GLib.Variant('(o)', ('/',)))
    elif method == 'SetSecret':
        s = params.get_child_value(0).unpack()
        it['f2'] = list(s[1])
        it['f3'] = list(s[2])
        it['f4'] = s[3]
        it['modified'] = int(time.time())
        save()
        emit(COL, 'org.freedesktop.Secret.Collection', 'ItemChanged',
             GLib.Variant('(o)', (path,)))
        inv.return_value(None)
    else:
        unknown(inv, method)


def item_prop(c, info, pinfo, path, *a):
    it = STATE['items'].get(path)
    if it is None:
        return None
    n = pinfo if isinstance(pinfo, str) else pinfo.name
    if n == 'Label':
        return GLib.Variant('s', it['label'])
    if n == 'Type':
        return GLib.Variant('s', it['type'])
    if n == 'Attributes':
        return GLib.Variant('a{ss}', it['attrs'])
    if n == 'Created':
        return GLib.Variant('t', it['created'])
    if n == 'Modified':
        return GLib.Variant('t', it['modified'])
    if n == 'Locked':
        return GLib.Variant('b', False)
    if n == 'Secret':
        return GLib.Variant('(oayays)', (SESS, bytes(it['f2']), bytes(it['f3']), it['f4']))
    return None


# ---------------------------------------------------------------- start
def reg(path, xml, handler, prop):
    node = Gio.DBusNodeInfo.new_for_xml('<node>' + xml + '</node>')
    REG_IDS.append(conn.register_object(path, node.interfaces[0], handler, prop))


def on_name(c, name, *a):
    reg(ROOT, SVC_XML, svc_method, svc_prop)
    reg(COL, COL_XML, col_method, col_prop)
    reg(ALIAS, COL_XML, col_method, col_prop)
    for p in STATE['items']:
        reg(p, ITEM_XML, item_method, item_prop)


def on_vanish(c, name, *a):
    pass  # тримимось: ім'я може повернутися, вихід лише за сигналом


Gio.bus_own_name(Gio.BusType.SESSION, NAME,
                 Gio.BusNameOwnerFlags.ALLOW_REPLACEMENT | Gio.BusNameOwnerFlags.REPLACE,
                 on_name, on_vanish)
loop.run()