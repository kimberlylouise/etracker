const express = require('express');
const cors = require('cors');
const multer = require('multer');
const app = express();
const PORT = 3001;

app.use(cors());
app.use(express.json());

let certificates = [
  { id: 1, name: "Juan Dela Cruz", program: "Community Nutrition Seminar", date: "2025-04-21", status: "Issued" },
  { id: 2, name: "Maria Santos", program: "Livelihood Training", date: "2025-05-02", status: "Pending" }
];

// List all certificates
app.get('/api/certificates', (req, res) => {
  res.json(certificates);
});

// Generate/issue certificates for a program and participant type
app.post('/api/certificates/generate', (req, res) => {
  const { program, participantType } = req.body;
  // Dummy: Add a new certificate
  const newCert = {
    id: certificates.length + 1,
    name: `New ${participantType}`,
    program,
    date: new Date().toISOString().slice(0, 10),
    status: "Issued"
  };
  certificates.push(newCert);
  res.json({ message: "Certificates generated!", certificate: newCert });
});

// Issue a pending certificate
app.post('/api/certificates/issue/:id', (req, res) => {
  const cert = certificates.find(c => c.id === parseInt(req.params.id));
  if (cert) {
    cert.status = "Issued";
    cert.date = new Date().toISOString().slice(0, 10);
    res.json({ message: "Certificate issued!", certificate: cert });
  } else {
    res.status(404).json({ message: "Certificate not found" });
  }
});

// Download certificate (dummy)
app.get('/api/certificates/download/:id', (req, res) => {
  // In real app, send the PDF file
  res.json({ message: `Download for certificate ${req.params.id}` });
});

// Upload template (dummy)
const upload = multer({ dest: 'uploads/' });
app.post('/api/certificates/template', upload.single('template'), (req, res) => {
  res.json({ message: "Template uploaded!", file: req.file });
});

// Search certificates
app.get('/api/certificates/search', (req, res) => {
  const q = (req.query.q || '').toLowerCase();
  const results = certificates.filter(c =>
    c.name.toLowerCase().includes(q) || c.program.toLowerCase().includes(q)
  );
  res.json(results);
});

app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});