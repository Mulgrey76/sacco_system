import { useMemo, useState } from 'react';
import { FaSearch, FaChevronUp, FaChevronDown, FaFilePdf, FaFileExcel } from 'react-icons/fa';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import * as XLSX from 'xlsx';

const PAGE_SIZE = 10;

/**
 * Generic, reusable data table.
 *
 * columns: [{ key, label, sortable?, render?(row) }]
 *   - `key` is used for both sorting and as the default cell value (row[key])
 *   - `render(row)` overrides the cell content (e.g. for badges/links)
 *     while sorting still uses the raw row[key] value underneath.
 * data: array of row objects (must each have a stable `id`)
 * searchKeys: which row fields the search box filters against
 * exportFilename: base filename for PDF/Excel downloads
 * onRowClick(row): optional
 */
export default function DataTable({ columns, data, searchKeys = [], exportFilename = 'export', onRowClick }) {
  const [search, setSearch] = useState('');
  const [sortKey, setSortKey] = useState(null);
  const [sortDir, setSortDir] = useState('asc');
  const [page, setPage] = useState(1);

  const filtered = useMemo(() => {
    if (!search.trim()) return data;
    const term = search.toLowerCase();
    return data.filter((row) =>
      searchKeys.some((key) => String(row[key] ?? '').toLowerCase().includes(term))
    );
  }, [data, search, searchKeys]);

  const sorted = useMemo(() => {
    if (!sortKey) return filtered;
    return [...filtered].sort((a, b) => {
      const av = a[sortKey], bv = b[sortKey];
      if (av === bv) return 0;
      const result = av > bv ? 1 : -1;
      return sortDir === 'asc' ? result : -result;
    });
  }, [filtered, sortKey, sortDir]);

  const totalPages = Math.max(1, Math.ceil(sorted.length / PAGE_SIZE));
  const pageRows = sorted.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  function toggleSort(key) {
    if (sortKey === key) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortKey(key);
      setSortDir('asc');
    }
    setPage(1);
  }

  function exportPdf() {
    const doc = new jsPDF();
    autoTable(doc, {
      head: [columns.map((c) => c.label)],
      body: sorted.map((row) => columns.map((c) => String(row[c.key] ?? ''))),
      styles: { fontSize: 8 },
      headStyles: { fillColor: [37, 99, 235] },
    });
    doc.save(`${exportFilename}.pdf`);
  }

  function exportExcel() {
    const rows = sorted.map((row) =>
      Object.fromEntries(columns.map((c) => [c.label, row[c.key]]))
    );
    const sheet = XLSX.utils.json_to_sheet(rows);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, sheet, 'Data');
    XLSX.writeFile(workbook, `${exportFilename}.xlsx`);
  }

  return (
    <div className="bg-card border border-light-border rounded-2xl overflow-hidden shadow-sm">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 border-b border-light-border">
        <div className="relative w-full sm:w-64">
          <FaSearch size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            placeholder="Search..."
            className="w-full pl-9 pr-3 py-2 border border-light-border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
          />
        </div>
        <div className="flex gap-2">
          <button
            onClick={exportPdf}
            className="flex items-center gap-1.5 text-xs font-medium text-slate-600 border border-light-border px-3 py-2 rounded-lg hover:bg-slate-50"
          >
            <FaFilePdf size={12} className="text-danger" /> PDF
          </button>
          <button
            onClick={exportExcel}
            className="flex items-center gap-1.5 text-xs font-medium text-slate-600 border border-light-border px-3 py-2 rounded-lg hover:bg-slate-50"
          >
            <FaFileExcel size={12} className="text-success" /> Excel
          </button>
        </div>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-slate-50 text-slate-600 text-left">
            <tr>
              {columns.map((col) => (
                <th
                  key={col.key}
                  onClick={() => col.sortable !== false && toggleSort(col.key)}
                  className={`px-4 py-3 font-semibold whitespace-nowrap ${col.sortable !== false ? 'cursor-pointer select-none hover:text-secondary' : ''}`}
                >
                  <span className="flex items-center gap-1">
                    {col.label}
                    {sortKey === col.key && (sortDir === 'asc' ? <FaChevronUp size={9} /> : <FaChevronDown size={9} />)}
                  </span>
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {pageRows.length === 0 && (
              <tr>
                <td colSpan={columns.length} className="px-4 py-10 text-center text-slate-400">
                  No results found.
                </td>
              </tr>
            )}
            {pageRows.map((row) => (
              <tr
                key={row.id}
                onClick={() => onRowClick?.(row)}
                className={`hover:bg-slate-50 transition-colors ${onRowClick ? 'cursor-pointer' : ''}`}
              >
                {columns.map((col) => (
                  <td key={col.key} className="px-4 py-3 text-body-text whitespace-nowrap">
                    {col.render ? col.render(row) : row[col.key]}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {totalPages > 1 && (
        <div className="flex items-center justify-between px-4 py-3 border-t border-light-border text-sm">
          <span className="text-slate-500">
            Page {page} of {totalPages} — {sorted.length} result{sorted.length !== 1 ? 's' : ''}
          </span>
          <div className="flex gap-1">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page === 1}
              className="px-3 py-1.5 rounded-lg border border-light-border disabled:opacity-40 hover:bg-slate-50"
            >
              Prev
            </button>
            <button
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={page === totalPages}
              className="px-3 py-1.5 rounded-lg border border-light-border disabled:opacity-40 hover:bg-slate-50"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
