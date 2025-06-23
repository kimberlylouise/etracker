// Express backend for Programs and Sessions

const express = require('express');
const router = express.Router();
const mysql = require('mysql2/promise');

// Update with your own DB config
const pool = mysql.createPool({
  host: 'localhost',
  user: 'root',
  password: 'yourpassword',
  database: 'etracker',
});

// --- PROGRAMS ---

// Get all programs
router.get('/programs', async (req, res) => {
  const [rows] = await pool.query('SELECT * FROM programs');
  res.json(rows);
});

// Create program
router.post('/programs', async (req, res) => {
  const { program_name, department, start_date, end_date, location, max_students, description, status, faculty_id } = req.body;
  const [result] = await pool.query(
    `INSERT INTO programs (program_name, department, start_date, end_date, location, max_students, description, status, faculty_id, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
    [program_name, department, start_date, end_date, location, max_students, description, status, faculty_id || null]
  );
  res.json({ id: result.insertId });
});

// Update program
router.put('/programs/:id', async (req, res) => {
  const { program_name, department, start_date, end_date, location, max_students, description, status, faculty_id } = req.body;
  await pool.query(
    `UPDATE programs SET program_name=?, department=?, start_date=?, end_date=?, location=?, max_students=?, description=?, status=?, faculty_id=?
     WHERE id=?`,
    [program_name, department, start_date, end_date, location, max_students, description, status, faculty_id || null, req.params.id]
  );
  res.json({ success: true });
});

// Delete program
router.delete('/programs/:id', async (req, res) => {
  await pool.query('DELETE FROM programs WHERE id=?', [req.params.id]);
  await pool.query('DELETE FROM program_sessions WHERE program_id=?', [req.params.id]);
  res.json({ success: true });
});

// --- SESSIONS ---

// Get all sessions for a program
router.get('/programs/:programId/sessions', async (req, res) => {
  const [rows] = await pool.query('SELECT * FROM program_sessions WHERE program_id=?', [req.params.programId]);
  res.json(rows);
});

// Create session for a program
router.post('/programs/:programId/sessions', async (req, res) => {
  const { session_title, session_date, session_start, session_end, location } = req.body;
  const [result] = await pool.query(
    `INSERT INTO program_sessions (program_id, session_title, session_date, session_start, session_end, location)
     VALUES (?, ?, ?, ?, ?, ?)`,
    [req.params.programId, session_title, session_date, session_start, session_end, location]
  );
  res.json({ id: result.insertId });
});

// Update session
router.put('/sessions/:id', async (req, res) => {
  const { session_title, session_date, session_start, session_end, location } = req.body;
  await pool.query(
    `UPDATE program_sessions SET session_title=?, session_date=?, session_start=?, session_end=?, location=?
     WHERE id=?`,
    [session_title, session_date, session_start, session_end, location, req.params.id]
  );
  res.json({ success: true });
});

// Delete session
router.delete('/sessions/:id', async (req, res) => {
  await pool.query('DELETE FROM program_sessions WHERE id=?', [req.params.id]);
  res.json({ success: true });
});

module.exports = router;