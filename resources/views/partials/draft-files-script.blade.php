@once
<script>
(function () {
    if (window.RedasDraftFiles) return;

    var DB_NAME = 'redas_draft_files_v1';
    var STORE_NAME = 'draft_files';

    function openDb() {
        return new Promise(function (resolve, reject) {
            if (!window.indexedDB) {
                resolve(null);
                return;
            }
            var req = window.indexedDB.open(DB_NAME, 1);
            req.onupgradeneeded = function (e) {
                var db = e.target.result;
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    db.createObjectStore(STORE_NAME, { keyPath: 'key' });
                }
            };
            req.onsuccess = function (e) { resolve(e.target.result); };
            req.onerror = function () { resolve(null); };
        });
    }

    function fileToRecord(file) {
        return file.arrayBuffer().then(function (buffer) {
            return {
                name: file.name,
                type: file.type || 'application/octet-stream',
                lastModified: file.lastModified || Date.now(),
                buffer: buffer
            };
        });
    }

    function recordToFile(rec) {
        return new File([rec.buffer], rec.name, {
            type: rec.type || 'application/octet-stream',
            lastModified: rec.lastModified || Date.now()
        });
    }

    window.RedasDraftFiles = {
        save: function (draftKey, form) {
            if (!draftKey || !form) return Promise.resolve(0);
            var fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
            var inputPromises = fileInputs.map(function (input, idx) {
                var files = Array.from(input.files || []);
                if (!files.length) return Promise.resolve(null);
                return Promise.all(files.map(fileToRecord)).then(function (serialized) {
                    return {
                        id: input.id || '',
                        name: input.name || '',
                        index: idx,
                        files: serialized
                    };
                });
            });

            return Promise.all(inputPromises).then(function (entries) {
                var nonEmpty = entries.filter(Boolean);
                var totalFiles = nonEmpty.reduce(function (sum, item) { return sum + item.files.length; }, 0);
                return openDb().then(function (db) {
                    if (!db) return totalFiles;
                    return new Promise(function (resolve) {
                        try {
                            var tx = db.transaction(STORE_NAME, 'readwrite');
                            var store = tx.objectStore(STORE_NAME);
                            if (nonEmpty.length) {
                                store.put({ key: draftKey, updatedAt: Date.now(), inputs: nonEmpty });
                            } else {
                                store.delete(draftKey);
                            }
                            tx.oncomplete = function () { resolve(totalFiles); };
                            tx.onerror = function () { resolve(totalFiles); };
                        } catch (e) {
                            resolve(totalFiles);
                        }
                    });
                });
            }).catch(function () { return 0; });
        },

        restore: function (draftKey, form) {
            if (!draftKey || !form) return Promise.resolve(0);
            return openDb().then(function (db) {
                if (!db) return 0;
                return new Promise(function (resolve) {
                    try {
                        var tx = db.transaction(STORE_NAME, 'readonly');
                        var req = tx.objectStore(STORE_NAME).get(draftKey);
                        req.onsuccess = function () {
                            var record = req.result;
                            if (!record || !Array.isArray(record.inputs) || !record.inputs.length) {
                                resolve(0);
                                return;
                            }
                            var fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
                            var restoredCount = 0;
                            record.inputs.forEach(function (savedInput) {
                                var target = null;
                                if (savedInput.id) {
                                    target = form.querySelector('#' + CSS.escape(savedInput.id));
                                }
                                if (!target && savedInput.name) {
                                    target = form.querySelector('input[type="file"][name="' + CSS.escape(savedInput.name) + '"]');
                                }
                                if (!target && typeof savedInput.index === 'number') {
                                    target = fileInputs[savedInput.index] || null;
                                }
                                if (!target || !Array.isArray(savedInput.files) || !savedInput.files.length) return;

                                try {
                                    var dt = new DataTransfer();
                                    var restoredFiles = [];
                                    savedInput.files.forEach(function (rec) {
                                        var f = recordToFile(rec);
                                        dt.items.add(f);
                                        restoredFiles.push(f);
                                    });
                                    target.files = dt.files;
                                    restoredCount += restoredFiles.length;
                                    target.dispatchEvent(new CustomEvent('redas:files-restored', {
                                        bubbles: true,
                                        detail: { files: restoredFiles }
                                    }));
                                } catch (e) {}
                            });
                            resolve(restoredCount);
                        };
                        req.onerror = function () { resolve(0); };
                    } catch (e) {
                        resolve(0);
                    }
                });
            }).catch(function () { return 0; });
        },

        clear: function (draftKey) {
            if (!draftKey) return Promise.resolve();
            return openDb().then(function (db) {
                if (!db) return;
                return new Promise(function (resolve) {
                    try {
                        var tx = db.transaction(STORE_NAME, 'readwrite');
                        tx.objectStore(STORE_NAME).delete(draftKey);
                        tx.oncomplete = function () { resolve(); };
                        tx.onerror = function () { resolve(); };
                    } catch (e) { resolve(); }
                });
            }).catch(function () {});
        },

        clearPrefix: function (prefix) {
            if (!prefix) return Promise.resolve();
            return openDb().then(function (db) {
                if (!db) return;
                return new Promise(function (resolve) {
                    try {
                        var tx = db.transaction(STORE_NAME, 'readwrite');
                        var store = tx.objectStore(STORE_NAME);
                        var cursorReq = store.openCursor();
                        cursorReq.onsuccess = function (e) {
                            var cursor = e.target.result;
                            if (!cursor) return;
                            if (typeof cursor.key === 'string' && cursor.key.indexOf(prefix) === 0) {
                                cursor.delete();
                            }
                            cursor.continue();
                        };
                        tx.oncomplete = function () { resolve(); };
                        tx.onerror = function () { resolve(); };
                    } catch (e) { resolve(); }
                });
            }).catch(function () {});
        }
    };
})();
</script>
@endonce
