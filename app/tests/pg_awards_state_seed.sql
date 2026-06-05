--
-- PostgreSQL database dump
--

\restrict H160G12AGVsF07zfEBbJD8gb9HzrG9PzxnrURpp9PkeX7X4gCvd3INstbsTSeT4

-- Dumped from database version 16.14 (Debian 16.14-1.pgdg13+1)
-- Dumped by pg_dump version 16.14 (Debian 16.14-1.pgdg12+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: awards_recommendation_statuses; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.awards_recommendation_statuses (id, name, sort_order, created, modified, created_by, modified_by, deleted) VALUES (1, 'In Progress', 1, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_statuses (id, name, sort_order, created, modified, created_by, modified_by, deleted) VALUES (2, 'Scheduling', 2, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_statuses (id, name, sort_order, created, modified, created_by, modified_by, deleted) VALUES (3, 'To Give', 3, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_statuses (id, name, sort_order, created, modified, created_by, modified_by, deleted) VALUES (4, 'Closed', 4, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL);


--
-- Data for Name: awards_recommendation_states; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (1, 1, 'Submitted', 1, false, false, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, false);
INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (2, 1, 'In Consideration', 2, false, false, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, false);
INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (3, 1, 'Awaiting Feedback', 3, false, false, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, false);
INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (4, 1, 'Deferred till Later', 4, false, false, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, false);
INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (5, 1, 'King Approved', 5, false, false, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, false);
INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (6, 1, 'Queen Approved', 6, false, false, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, false);
INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (7, 2, 'Need to Schedule', 1, true, false, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, false);
INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (8, 3, 'Scheduled', 1, true, false, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, false);
INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (9, 3, 'Announced Not Given', 2, false, false, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, false);
INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (10, 4, 'Given', 1, true, false, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, false);
INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (11, 4, 'No Action', 2, false, true, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, false);
INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (12, 1, 'Linked', 99, false, true, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, true);
INSERT INTO public.awards_recommendation_states (id, status_id, name, sort_order, supports_gathering, is_hidden, created, modified, created_by, modified_by, deleted, is_system) VALUES (13, 4, 'Linked - Closed', 99, false, true, '2026-05-16 00:30:09', NULL, NULL, NULL, NULL, true);


--
-- Data for Name: awards_recommendation_state_field_rules; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (1, 7, 'planToGiveBlockTarget', 'Visible', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (2, 7, 'domainTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (3, 7, 'awardTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (4, 7, 'specialtyTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (5, 7, 'scaMemberTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (6, 7, 'branchTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (7, 8, 'planToGiveEventTarget', 'Required', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (8, 8, 'planToGiveBlockTarget', 'Visible', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (9, 8, 'domainTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (10, 8, 'awardTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (11, 8, 'specialtyTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (12, 8, 'scaMemberTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (13, 8, 'branchTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (14, 10, 'planToGiveEventTarget', 'Required', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (15, 10, 'givenDateTarget', 'Required', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (16, 10, 'planToGiveBlockTarget', 'Visible', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (17, 10, 'givenBlockTarget', 'Visible', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (18, 10, 'domainTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (19, 10, 'awardTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (20, 10, 'specialtyTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (21, 10, 'scaMemberTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (22, 10, 'branchTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (23, 10, 'close_reason', 'Set', 'Given', '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (24, 11, 'closeReasonTarget', 'Required', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (25, 11, 'closeReasonBlockTarget', 'Visible', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (26, 11, 'closeReasonTarget', 'Visible', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (27, 11, 'domainTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (28, 11, 'awardTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (29, 11, 'specialtyTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (30, 11, 'scaMemberTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (31, 11, 'branchTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (32, 11, 'courtAvailabilityTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (33, 11, 'callIntoCourtTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (34, 12, 'domainTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (35, 12, 'awardTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (36, 12, 'specialtyTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (37, 12, 'scaMemberTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (38, 12, 'branchTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (39, 12, 'courtAvailabilityTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (40, 12, 'callIntoCourtTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (41, 12, 'reasonTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (42, 12, 'contactEmailTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (43, 12, 'contactNumberTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (44, 12, 'personToNotifyTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (45, 13, 'domainTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (46, 13, 'awardTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (47, 13, 'specialtyTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (48, 13, 'scaMemberTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (49, 13, 'branchTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (50, 13, 'courtAvailabilityTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (51, 13, 'callIntoCourtTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (52, 13, 'reasonTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (53, 13, 'contactEmailTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (54, 13, 'contactNumberTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_field_rules (id, state_id, field_target, rule_type, rule_value, created, modified, created_by, modified_by) VALUES (55, 13, 'personToNotifyTarget', 'Disabled', NULL, '2026-05-16 00:30:09', NULL, NULL, NULL);


--
-- Data for Name: awards_recommendation_state_transitions; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (1, 1, 2, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (2, 1, 3, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (3, 1, 4, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (4, 1, 5, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (5, 1, 6, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (6, 1, 7, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (7, 1, 8, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (8, 1, 9, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (9, 1, 10, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (10, 1, 11, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (11, 2, 1, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (12, 2, 3, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (13, 2, 4, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (14, 2, 5, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (15, 2, 6, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (16, 2, 7, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (17, 2, 8, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (18, 2, 9, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (19, 2, 10, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (20, 2, 11, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (21, 3, 1, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (22, 3, 2, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (23, 3, 4, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (24, 3, 5, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (25, 3, 6, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (26, 3, 7, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (27, 3, 8, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (28, 3, 9, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (29, 3, 10, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (30, 3, 11, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (31, 4, 1, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (32, 4, 2, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (33, 4, 3, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (34, 4, 5, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (35, 4, 6, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (36, 4, 7, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (37, 4, 8, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (38, 4, 9, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (39, 4, 10, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (40, 4, 11, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (41, 5, 1, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (42, 5, 2, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (43, 5, 3, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (44, 5, 4, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (45, 5, 6, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (46, 5, 7, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (47, 5, 8, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (48, 5, 9, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (49, 5, 10, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (50, 5, 11, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (51, 6, 1, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (52, 6, 2, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (53, 6, 3, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (54, 6, 4, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (55, 6, 5, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (56, 6, 7, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (57, 6, 8, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (58, 6, 9, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (59, 6, 10, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (60, 6, 11, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (61, 7, 1, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (62, 7, 2, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (63, 7, 3, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (64, 7, 4, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (65, 7, 5, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (66, 7, 6, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (67, 7, 8, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (68, 7, 9, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (69, 7, 10, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (70, 7, 11, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (71, 8, 1, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (72, 8, 2, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (73, 8, 3, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (74, 8, 4, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (75, 8, 5, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (76, 8, 6, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (77, 8, 7, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (78, 8, 9, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (79, 8, 10, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (80, 8, 11, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (81, 9, 1, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (82, 9, 2, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (83, 9, 3, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (84, 9, 4, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (85, 9, 5, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (86, 9, 6, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (87, 9, 7, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (88, 9, 8, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (89, 9, 10, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (90, 9, 11, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (91, 10, 1, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (92, 10, 2, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (93, 10, 3, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (94, 10, 4, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (95, 10, 5, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (96, 10, 6, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (97, 10, 7, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (98, 10, 8, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (99, 10, 9, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (100, 10, 11, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (101, 11, 1, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (102, 11, 2, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (103, 11, 3, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (104, 11, 4, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (105, 11, 5, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (106, 11, 6, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (107, 11, 7, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (108, 11, 8, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (109, 11, 9, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (110, 11, 10, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (111, 1, 12, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (112, 12, 1, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (113, 2, 12, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (114, 12, 2, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (115, 3, 12, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (116, 12, 3, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (117, 4, 12, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (118, 12, 4, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (119, 5, 12, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (120, 12, 5, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (121, 6, 12, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (122, 12, 6, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (123, 7, 12, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (124, 12, 7, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (125, 8, 12, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (126, 12, 8, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (127, 9, 12, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (128, 12, 9, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (129, 10, 12, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (130, 12, 10, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (131, 11, 12, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (132, 12, 11, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (133, 1, 13, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (134, 13, 1, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (135, 2, 13, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (136, 13, 2, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (137, 3, 13, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (138, 13, 3, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (139, 4, 13, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (140, 13, 4, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (141, 5, 13, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (142, 13, 5, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (143, 6, 13, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (144, 13, 6, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (145, 7, 13, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (146, 13, 7, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (147, 8, 13, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (148, 13, 8, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (149, 9, 13, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (150, 13, 9, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (151, 10, 13, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (152, 13, 10, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (153, 11, 13, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (154, 13, 11, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (155, 12, 13, '2026-05-16 00:30:09', NULL, NULL, NULL);
INSERT INTO public.awards_recommendation_state_transitions (id, from_state_id, to_state_id, created, modified, created_by, modified_by) VALUES (156, 13, 12, '2026-05-16 00:30:09', NULL, NULL, NULL);


--
-- Name: awards_recommendation_state_field_rules_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.awards_recommendation_state_field_rules_id_seq', 55, true);


--
-- Name: awards_recommendation_state_transitions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.awards_recommendation_state_transitions_id_seq', 210, true);


--
-- Name: awards_recommendation_states_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.awards_recommendation_states_id_seq', 15, true);


--
-- Name: awards_recommendation_statuses_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.awards_recommendation_statuses_id_seq', 4, true);


--
-- PostgreSQL database dump complete
--

\unrestrict H160G12AGVsF07zfEBbJD8gb9HzrG9PzxnrURpp9PkeX7X4gCvd3INstbsTSeT4
