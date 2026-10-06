<?php
declare(strict_types=1);

namespace LeadsPilot;

use RuntimeException;

class Company
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function getCompaniesByAgency(int $agencyId): array
    {
        $sql = "SELECT c.id, c.agency_id, c.name, c.website, c.phone, c.address, c.created_at,
                       COUNT(DISTINCT cont.id) AS contact_count,
                       COUNT(DISTINCT d.id) AS deal_count
                FROM companies c
                LEFT JOIN contacts cont ON cont.company_id = c.id
                LEFT JOIN deals d ON d.company_id = c.id
                WHERE c.agency_id = :agency_id
                GROUP BY c.id
                ORDER BY c.name ASC";

        return $this->db->fetchAll($sql, ['agency_id' => $agencyId]);
    }

    public function createCompany(int $agencyId, array $data): int
    {
        $name    = trim($data['name'] ?? '');
        $website = trim($data['website'] ?? '');
        $phone   = trim($data['phone'] ?? '');
        $address = trim($data['address'] ?? '');

        if (empty($name)) {
            throw new RuntimeException("Company name is required.");
        }

        $sql = "INSERT INTO companies (agency_id, name, website, phone, address)
                VALUES (:agency_id, :name, :website, :phone, :address)";

        $this->db->query($sql, [
            'agency_id' => $agencyId,
            'name'      => $name,
            'website'   => $website,
            'phone'     => $phone,
            'address'   => $address,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateCompany(int $agencyId, int $companyId, array $data): bool
    {
        $name    = trim($data['name'] ?? '');
        $website = trim($data['website'] ?? '');
        $phone   = trim($data['phone'] ?? '');
        $address = trim($data['address'] ?? '');

        if (empty($name)) {
            throw new RuntimeException("Company name is required.");
        }

        $sql = "UPDATE companies 
                SET name = :name, website = :website, phone = :phone, address = :address 
                WHERE id = :id AND agency_id = :agency_id";

        $this->db->query($sql, [
            'name'      => $name,
            'website'   => $website,
            'phone'     => $phone,
            'address'   => $address,
            'id'        => $companyId,
            'agency_id' => $agencyId,
        ]);

        return true;
    }

    public function deleteCompany(int $agencyId, int $companyId): bool
    {
        $sql = "DELETE FROM companies WHERE id = :id AND agency_id = :agency_id";
        $this->db->query($sql, ['id' => $companyId, 'agency_id' => $agencyId]);
        return true;
    }
}