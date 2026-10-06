<?php
declare(strict_types=1);

namespace LeadsPilot;

use RuntimeException;

class Contact
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Fetch all contacts for a specific agency workspace with company join data.
     */
    public function getContactsByAgency(int $agencyId): array
    {
        $sql = "SELECT ct.id, ct.agency_id, ct.company_id, ct.first_name, ct.last_name, 
                       ct.email, ct.phone, ct.position, ct.status, ct.created_at,
                       c.name AS company_name
                FROM contacts ct
                LEFT JOIN companies c ON ct.company_id = c.id
                WHERE ct.agency_id = :agency_id
                ORDER BY ct.last_name ASC, ct.first_name ASC";

        return $this->db->fetchAll($sql, ['agency_id' => $agencyId]);
    }

    /**
     * Fetch contacts associated with a specific company.
     */
    public function getContactsByCompany(int $agencyId, int $companyId): array
    {
        $sql = "SELECT id, first_name, last_name, email, phone, position, status
                FROM contacts
                WHERE agency_id = :agency_id AND company_id = :company_id
                ORDER BY last_name ASC, first_name ASC";

        return $this->db->fetchAll($sql, [
            'agency_id'  => $agencyId,
            'company_id' => $companyId,
        ]);
    }

    /**
     * Fetch a single contact by ID scoped to agency workspace.
     */
    public function getContactById(int $agencyId, int $contactId): ?array
    {
        $sql = "SELECT ct.id, ct.agency_id, ct.company_id, ct.first_name, ct.last_name, 
                       ct.email, ct.phone, ct.position, ct.status, ct.created_at, ct.updated_at,
                       c.name AS company_name
                FROM contacts ct
                LEFT JOIN companies c ON ct.company_id = c.id
                WHERE ct.id = :id AND ct.agency_id = :agency_id
                LIMIT 1";

        return $this->db->fetchOne($sql, [
            'id'        => $contactId,
            'agency_id' => $agencyId,
        ]);
    }

    /**
     * Create a new contact within an agency workspace.
     */
    public function createContact(int $agencyId, array $data): int
    {
        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');
        $email     = trim($data['email'] ?? '');

        if (empty($firstName) || empty($lastName) || empty($email)) {
            throw new RuntimeException("First name, last name, and email are required fields.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Invalid email address provided.");
        }

        $sql = "INSERT INTO contacts (agency_id, company_id, first_name, last_name, email, phone, position, status)
                VALUES (:agency_id, :company_id, :first_name, :last_name, :email, :phone, :position, :status)";

        $params = [
            'agency_id'  => $agencyId,
            'company_id' => !empty($data['company_id']) ? (int) $data['company_id'] : null,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => strtolower($email),
            'phone'      => !empty($data['phone']) ? trim($data['phone']) : null,
            'position'   => !empty($data['position']) ? trim($data['position']) : null,
            'status'     => $data['status'] ?? 'lead',
        ];

        $this->db->query($sql, $params);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update an existing contact scoped to agency workspace.
     */
    public function updateContact(int $agencyId, int $contactId, array $data): bool
    {
        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');
        $email     = trim($data['email'] ?? '');

        if (empty($firstName) || empty($lastName) || empty($email)) {
            throw new RuntimeException("First name, last name, and email are required fields.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Invalid email address provided.");
        }

        $sql = "UPDATE contacts 
                SET company_id = :company_id, first_name = :first_name, last_name = :last_name, 
                    email = :email, phone = :phone, position = :position, status = :status, updated_at = NOW()
                WHERE id = :id AND agency_id = :agency_id";

        $params = [
            'company_id' => !empty($data['company_id']) ? (int) $data['company_id'] : null,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => strtolower($email),
            'phone'      => !empty($data['phone']) ? trim($data['phone']) : null,
            'position'   => !empty($data['position']) ? trim($data['position']) : null,
            'status'     => $data['status'] ?? 'lead',
            'id'         => $contactId,
            'agency_id'  => $agencyId,
        ];

        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a contact scoped to agency workspace.
     */
    public function deleteContact(int $agencyId, int $contactId): bool
    {
        $sql = "DELETE FROM contacts WHERE id = :id AND agency_id = :agency_id";
        $stmt = $this->db->query($sql, [
            'id'        => $contactId,
            'agency_id' => $agencyId,
        ]);

        return $stmt->rowCount() > 0;
    }
}